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
        $link = $this->access->account($r->user());
        $dto = $this->billing->customer($link->external_account_id);
        abort_unless($dto->data['external_customer_id'] === $link->external_customer_id, 404);

        return response()->json(['data' => $dto, 'meta' => $this->meta()]);
    }

    public function index(BillSearchRequest $r)
    {
        $link = $this->access->account($r->user());
        $result = $this->billing->bills($this->access->filters($r->user(), $r->filters()));
        foreach ($result['data'] as $row) {
            abort_unless($row['external_customer_id'] === $link->external_customer_id && $row['external_account_id'] === $link->external_account_id, 404);
        }

        return response()->json([...$result, 'meta' => [...$result['meta'], ...$this->meta()]]);
    }

    public function show(Request $r, string $bill)
    {
        $link = $this->access->account($r->user());
        $dto = $this->billing->bill($bill, $link->external_account_id);
        abort_unless($dto->data['customer']['external_customer_id'] === $link->external_customer_id, 404);

        return response()->json(['data' => $dto, 'meta' => $this->meta()]);
    }

    public function quote(Request $r, string $bill)
    {
        $link = $this->access->account($r->user());
        $dto = $this->billing->quote($bill, $link->external_account_id);
        abort_unless($dto->data['external_customer_id'] === $link->external_customer_id, 404);

        return response()->json(['data' => $dto, 'meta' => $this->meta()]);
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
