<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;

class ReconcileService
{
    public function __construct(private PaymentGateway $gateway, private PaymentFinalizer $finalizer) {}

    public function run(PaymentAttempt $p): void
    {
        $e = $p->gateway_transaction_id ? $this->gateway->fetchTransaction($p->gateway_transaction_id) : $this->gateway->queryStatus($p->reference);
        if ($e) {
            $this->finalizer->finalize($e);
        } elseif ($p->created_at->lt(now()->subDay())) {
            $p->update(['sync_status' => 'needs_review', 'review_reason' => 'GATEWAY_RESULT_UNRESOLVED']);
            PaymentEvents::add($p->id, 'gateway.reconcile_unresolved');
        }
    }
}
