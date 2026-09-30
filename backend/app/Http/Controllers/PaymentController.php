<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Integrations\Gateways\FakeGateway;
use App\Models\PaymentAttempt;
use App\Models\PaymentReceipt;
use App\Services\Payments\InitiatePayment;
use App\Services\Payments\PaymentFinalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function initiate(Request $r, string $bill, InitiatePayment $service)
    {
        $p = $service->execute($r->user(), $bill);

        return response()->json(['data' => $this->view($p)], $p->checkout_url ? 200 : 202);
    }

    public function index(Request $r)
    {
        $r->validate(['page' => 'sometimes|integer|min:1', 'status' => 'sometimes|nullable|in:initiated,pending,succeeded,failed,cancelled,expired']);
        $q = PaymentAttempt::where('user_id', $r->user()->id)->latest();
        if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        }$page = $q->paginate(10);

        return response()->json(['data' => $page->getCollection()->map(fn ($p) => $this->view($p)), 'meta' => ['pagination' => ['page' => $page->currentPage(), 'per_page' => 10, 'total' => $page->total(), 'has_next' => $page->hasMorePages()]]]);
    }

    public function show(PaymentAttempt $payment)
    {
        Gate::authorize('view', $payment);

        return response()->json(['data' => $this->view($payment)]);
    }

    public function receipt(PaymentAttempt $payment)
    {
        Gate::authorize('view', $payment);
        $r = PaymentReceipt::where('payment_id', $payment->id)->firstOrFail();

        return response()->json(['data' => ['receipt' => $r->snapshot, 'sync_status' => $payment->sync_status, 'upstream_receipt_reference' => $payment->upstream_receipt_reference, 'billing_system_payment_id' => $payment->billing_system_payment_id]]);
    }

    public function callback(Request $r, PaymentGateway $gateway, PaymentFinalizer $finalizer)
    {
        $finalizer->finalize($gateway->verifyNotification($r));

        return response()->json(['data' => ['accepted' => true]]);
    }

    public function simulate(Request $r, PaymentAttempt $payment, PaymentFinalizer $finalizer)
    {
        abort_unless((string) $r->user()->id === (string) $payment->user_id, 404);
        $d = $r->validate(['outcome' => 'required|in:succeeded,failed,cancelled']);
        $gateway = app(PaymentGateway::class);
        abort_unless($gateway instanceof FakeGateway, 404);
        $finalizer->finalize($gateway->simulate($payment, $d['outcome']));

        return response()->json(['data' => $this->view($payment->fresh())]);
    }

    public function view(PaymentAttempt $p): array
    {
        return ['id' => $p->id, 'reference' => $p->reference, 'external_bill_id' => $p->external_bill_id, 'external_account_id' => $p->external_account_id, 'amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'status' => $p->status, 'sync_status' => $p->sync_status, 'checkout_url' => $p->checkout_url, 'verified_at' => $p->verified_at?->toIso8601String(), 'review_reason' => $p->review_reason, 'created_at' => $p->created_at->toIso8601String()];
    }
}
