<?php

namespace App\Services\Payments;

use App\Jobs\SyncPayment;
use App\Models\PaymentAttempt;
use App\Models\PaymentOutbox;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentFinalizer
{
    public function finalize(array $e): PaymentAttempt
    {
        return DB::transaction(function () use ($e) {
            $p = PaymentAttempt::where('reference', $e['reference'] ?? '')->lockForUpdate()->firstOrFail();
            $valid = ($e['merchant'] ?? null) === config('payments.merchant') && ($e['currency'] ?? null) === $p->currency && ($e['amount_minor'] ?? null) === $p->amount_minor && is_string($e['transaction_id'] ?? null) && (! $p->gateway_transaction_id || $p->gateway_transaction_id === $e['transaction_id']) && ! PaymentAttempt::where('gateway', $p->gateway)->where('gateway_transaction_id', $e['transaction_id'])->where('id', '!=', $p->id)->exists();
            if (! $valid) {
                if ($p->status !== 'succeeded') {
                    $p->update(['sync_status' => 'needs_review', 'review_reason' => 'GATEWAY_EVIDENCE_MISMATCH']);
                }PaymentEvents::add($p->id, 'gateway.evidence_rejected');

                return $p;
            }
            $status = $e['status'] ?? '';
            if ($p->status === 'succeeded') {
                PaymentEvents::add($p->id, 'gateway.duplicate_or_late_event');

                return $p;
            }
            if ($status !== 'succeeded') {
                if (in_array($status, ['failed', 'cancelled', 'expired'])) {
                    $p->update(['status' => $status, 'active_key' => null]);
                    PaymentEvents::add($p->id, 'gateway.'.$status);
                }

                // A pending/out-of-order response cannot reset a terminal state.
                return $p;
            }
            if (empty($e['verified_at']) || ! is_string($e['verified_at']) || ! strtotime($e['verified_at'])) {
                $p->update(['sync_status' => 'needs_review', 'review_reason' => 'MISSING_VERIFIED_TIME']);

                return $p;
            }
            $duplicate = PaymentAttempt::where('external_bill_id', $p->external_bill_id)->where('id', '!=', $p->id)->where(function ($q) {
                $q->whereNotNull('active_key')->orWhere('status', 'succeeded');
            })->exists();
            $p->update(['status' => 'succeeded', 'gateway_transaction_id' => $e['transaction_id'], 'verified_at' => $e['verified_at'], 'sync_status' => $duplicate ? 'needs_review' : 'pending', 'review_reason' => $duplicate ? 'POSSIBLE_DUPLICATE_COLLECTION' : null]);
            $snapshot = ['receipt_reference' => 'R-'.$p->id, 'portal_payment_id' => $p->id, 'external_bill_id' => $p->external_bill_id, 'external_account_id' => $p->external_account_id, 'external_customer_id' => $p->external_customer_id, 'amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'verified_at' => $p->verified_at->toIso8601String(), 'gateway_reference' => $p->gateway_transaction_id, 'external_transaction_reference' => $p->reference, 'billing_sync_status_at_issue' => $p->sync_status, 'bill_snapshot' => $p->bill_snapshot];
            PaymentReceipt::firstOrCreate(['payment_id' => $p->id], ['id' => (string) Str::uuid(), 'reference' => 'R-'.$p->id, 'snapshot' => $snapshot, 'created_at' => now()]);
            $payload = ['external_transaction_reference' => $p->reference, 'portal_payment_id' => $p->id, 'external_bill_id' => $p->external_bill_id, 'external_account_id' => $p->external_account_id, 'external_customer_id' => $p->external_customer_id, 'verified_amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'verified_payment_at' => $p->verified_at->toIso8601String(), 'gateway_identifier' => $p->gateway, 'gateway_transaction_id' => $p->gateway_transaction_id, 'idempotency_key' => $p->idempotency_key, 'quote_id' => $p->quote['quote_id'], 'reservation_id' => $p->quote['reservation_id']];
            PaymentOutbox::firstOrCreate(['payment_id' => $p->id], ['payload' => $payload, 'available_at' => $duplicate ? null : now()]);
            PaymentEvents::add($p->id, 'payment.verified', ['sync_status' => $p->sync_status]);
            if (! $duplicate) {
                SyncPayment::dispatch($p->id)->afterCommit();
            }

            return $p;
        });
    }
}
