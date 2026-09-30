<?php

namespace App\Http\Controllers;

use App\Contracts\BillingSystemClient;
use App\Http\Requests\BillSearchRequest;
use App\Services\Audit;
use App\Services\Billing\AccountAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BillingController extends Controller
{
    public function __construct(private BillingSystemClient $billing, private AccountAccess $access) {}

    public function customer(Request $r)
    {
        return response()->json(['data' => $this->billing->customer($this->access->account($r->user())->external_account_id), 'meta' => $this->meta()]);
    }

    public function index(BillSearchRequest $r)
    {
        $result = $this->billing->bills($this->access->filters($r->user(), $r->filters()));

        return response()->json([...$result, 'meta' => [...$result['meta'], ...$this->meta()]]);
    }

    public function show(Request $r, string $bill)
    {
        return response()->json(['data' => $this->billing->bill($bill, $this->access->account($r->user())->external_account_id), 'meta' => $this->meta()]);
    }

    public function quote(Request $r, string $bill)
    {
        return response()->json(['data' => $this->billing->quote($bill, $this->access->account($r->user())->external_account_id), 'meta' => $this->meta()]);
    }

    public function adminIndex(BillSearchRequest $r)
    {
        Gate::authorize('access-admin');
        Audit::record('billing.search', null, ['filters' => array_keys($r->filters())]);

        return response()->json($this->billing->bills($r->filters()));
    }

    public function adminShow(string $bill)
    {
        Gate::authorize('access-admin');
        Audit::record('billing.view', $bill);

        return response()->json(['data' => $this->billing->bill($bill), 'meta' => $this->meta()]);
    }

    public function health()
    {
        Gate::authorize('access-admin');

        return response()->json(['data' => [...$this->billing->health(), 'capabilities' => $this->billing->capabilities()]]);
    }

    private function meta(): array
    {
        return ['mode' => config('billing.mode'), 'fetched_at' => now()->toIso8601String(), 'is_snapshot' => false];
    }
}
