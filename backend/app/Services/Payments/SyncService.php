<?php

namespace App\Services\Payments;

use App\Contracts\BillingSystemClient;
use App\Exceptions\PortalException;
use App\Models\PaymentAttempt;
use App\Models\PaymentOutbox;
use Illuminate\Support\Facades\DB;

class SyncService
{
    public function __construct(private BillingSystemClient $billing) {}

    public function run(string $id): void
    {
        $out = DB::transaction(function () use ($id) {
            $p = PaymentAttempt::whereKey($id)->lockForUpdate()->firstOrFail();
            $o = PaymentOutbox::where('payment_id', $id)->lockForUpdate()->first();
            if (! $o || $p->status !== 'succeeded' || in_array($p->sync_status, ['synced', 'needs_review']) || ($o->lease_until && $o->lease_until->isFuture()) || ($o->available_at && $o->available_at->isFuture())) {
                return null;
            }$p->update(['sync_status' => 'syncing']);
            $o->update(['lease_until' => now()->addSeconds(60), 'attempts' => $o->attempts + 1]);

            return $o;
        });
        if (! $out) {
            return;
        }
        $caps = ['reference_lookup' => false, 'idempotent_posting' => false];
        try {
            if ($out->attempts > config('payments.max_sync_attempts')) {
                $this->review($id, 'RETRY_BUDGET_EXHAUSTED');

                return;
            }
            $caps = $this->billing->capabilities();
            $posted = $caps['reference_lookup'] ? $this->billing->lookupPayment($out->payload['external_transaction_reference']) : null;
            if (! $posted) {
                if ($out->ambiguous && ! $caps['idempotent_posting']) {
                    $this->review($id, 'AMBIGUOUS_NON_IDEMPOTENT_POST');

                    return;
                }
                // Persist before the call: a killed worker must not repeat an uncertain write.
                PaymentOutbox::where('payment_id', $id)->update(['ambiguous' => true]);
                $posted = $this->billing->registerPayment($out->payload);
            }
            $this->confirm($id, $out->payload, $posted);
        } catch (\Throwable $e) {
            $reason = $e instanceof PortalException ? $e->errorCode : 'BILLING_UNAVAILABLE';
            $ambiguous = (bool) PaymentOutbox::where('payment_id', $id)->value('ambiguous') || in_array($reason, ['WRITE_RESULT_UNKNOWN', 'UPSTREAM_SCHEMA_INVALID']);
            if ($ambiguous && $caps['reference_lookup']) {
                try {
                    $posted = $this->billing->lookupPayment($out->payload['external_transaction_reference']);
                    if ($posted) {
                        $this->confirm($id, $out->payload, $posted);

                        return;
                    }
                } catch (\Throwable $lookupFailure) {/* Persist the ambiguous outcome; never change key or amount. */
                }
            }
            if (in_array($reason, ['BALANCE_CHANGED', 'BILLING_CONFLICT', 'UPSTREAM_RELATIONSHIP_INVALID']) || ($ambiguous && ! $caps['idempotent_posting']) || $out->attempts >= config('payments.max_sync_attempts')) {
                $this->review($id, $reason);

                return;
            }
            DB::transaction(function () use ($id, $out, $reason, $ambiguous) {
                PaymentAttempt::whereKey($id)->update(['sync_status' => 'failed', 'review_reason' => $reason]);
                PaymentOutbox::where('payment_id', $id)->update(['ambiguous' => $out->ambiguous || $ambiguous, 'lease_until' => null, 'available_at' => now()->addSeconds([30, 120, 600, 1800][$out->attempts - 1] ?? 1800)]);
                PaymentEvents::add($id, 'billing.retry_scheduled', ['reason' => $reason, 'attempt' => $out->attempts]);
            });
        }
    }

    public function lookupOnly(PaymentAttempt $p): void
    {
        $o = PaymentOutbox::where('payment_id', $p->id)->firstOrFail();
        if (! $this->billing->capabilities()['reference_lookup']) {
            throw new PortalException('REFERENCE_LOOKUP_UNSUPPORTED');
        }$posted = $this->billing->lookupPayment($p->reference);
        if ($posted) {
            $this->confirm($p->id, $o->payload, $posted);
        } else {
            PaymentEvents::add($p->id, 'billing.lookup_not_found');
        }
    }

    private function confirm(string $id, array $sent, array $received): void
    {
        foreach (['external_transaction_reference', 'external_bill_id', 'external_account_id', 'external_customer_id', 'verified_amount_minor', 'currency'] as $k) {
            if (($received[$k] ?? null) !== $sent[$k]) {
                $this->review($id, 'UPSTREAM_POSTING_MISMATCH');

                return;
            }
        }
        if (($received['status'] ?? null) !== 'posted' || ! is_string($received['billing_system_payment_id'] ?? null)) {
            $this->review($id, 'UPSTREAM_NOT_POSTED');

            return;
        }
        DB::transaction(function () use ($id, $received) {
            $p = PaymentAttempt::whereKey($id)->lockForUpdate()->firstOrFail();
            $p->update(['sync_status' => 'synced', 'billing_system_payment_id' => $received['billing_system_payment_id'], 'upstream_receipt_reference' => $received['upstream_receipt_reference'] ?? null, 'review_reason' => null, 'active_key' => null]);
            PaymentOutbox::where('payment_id', $id)->update(['lease_until' => null, 'available_at' => null]);
            PaymentEvents::add($id, 'billing.synced');
        });
    }

    private function review(string $id, string $reason): void
    {
        DB::transaction(function () use ($id, $reason) {
            PaymentAttempt::whereKey($id)->update(['sync_status' => 'needs_review', 'review_reason' => $reason]);
            PaymentOutbox::where('payment_id', $id)->update(['lease_until' => null, 'available_at' => null]);
            PaymentEvents::add($id, 'billing.needs_review', ['reason' => $reason]);
        });
    }
}
