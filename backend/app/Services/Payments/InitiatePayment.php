<?php

namespace App\Services\Payments;

use App\Contracts\BillingSystemClient;
use App\Contracts\PaymentGateway;
use App\Exceptions\PortalException;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Billing\AccountAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitiatePayment
{
    public function __construct(private BillingSystemClient $billing, private PaymentGateway $gateway, private AccountAccess $access) {}

    public function execute(User $user, string $billId): PaymentAttempt
    {
        // There is no selected/documented live gateway yet. Fail closed.
        abort_unless(app()->environment(['local', 'testing']) && config('payments.gateway') === 'fake' && config('billing.mode') === 'mock', 503);
        $link = $this->access->account($user);
        $bill = $this->billing->bill($billId, $link->external_account_id)->data;
        abort_unless($bill['customer']['external_customer_id'] === $link->external_customer_id, 404);
        $existing = PaymentAttempt::where('external_bill_id', $billId)->where('user_id', $user->id)->where(function ($q) {
            $q->whereNotNull('active_key')->orWhere('status', 'succeeded');
        })->latest()->first();
        if ($existing) {
            return $existing;
        }
        $quote = $this->billing->quote($billId, $link->external_account_id)->data;
        if (! $quote['eligible'] || $quote['currency'] !== 'BDT' || $quote['payable_amount_minor'] === '0' || $quote['payable_amount_minor'] !== $quote['outstanding_balance_minor'] || $quote['collection_fee_minor'] !== '0' || $quote['total_charge_minor'] !== $quote['payable_amount_minor'] || Carbon::parse($quote['expires_at'])->isPast() || $quote['external_customer_id'] !== $link->external_customer_id || $quote['payable_amount_minor'] !== $bill['authoritative_totals']['outstanding_balance_minor']) {
            throw new PortalException('QUOTE_NOT_PAYABLE', 409);
        }
        $payment = DB::transaction(function () use ($user, $billId, $quote, $bill, $link) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = PaymentAttempt::where('external_bill_id', $billId)->where('user_id', $user->id)->where(function ($q) {
                $q->whereNotNull('active_key')->orWhere('status', 'succeeded');
            })->first();
            if ($existing) {
                return $existing;
            }
            $id = (string) Str::uuid();

            return PaymentAttempt::create(['id' => $id, 'user_id' => $user->id, 'external_bill_id' => $billId, 'external_account_id' => $link->external_account_id, 'external_customer_id' => $link->external_customer_id, 'reference' => 'portal-'.$id, 'idempotency_key' => 'post-'.$id, 'active_key' => hash('sha256', $billId), 'amount_minor' => $quote['payable_amount_minor'], 'currency' => 'BDT', 'status' => 'initiated', 'sync_status' => 'not_required', 'gateway' => 'fake', 'quote' => $quote, 'bill_snapshot' => $bill]);
        });
        if (! $payment->wasRecentlyCreated) {
            return $payment;
        }
        // External call outside the transaction; unknown results are reconciled by reference.
        try {
            $session = $this->gateway->createSession($payment);
            DB::transaction(function () use ($payment, $session) {
                $p = PaymentAttempt::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($p->status === 'initiated') {
                    $p->update(['status' => 'pending', 'gateway_transaction_id' => $session['transaction_id'], 'checkout_url' => $session['checkout_url']]);
                }PaymentEvents::add($p->id, 'gateway.session_created');
            });
        } catch (\Throwable $e) {
            $payment->update(['review_reason' => 'SESSION_RESULT_UNKNOWN']);
            PaymentEvents::add($payment->id, 'gateway.session_unknown');
        }

        return $payment->fresh();
    }
}
