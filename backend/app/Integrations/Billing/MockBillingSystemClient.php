<?php

namespace App\Integrations\Billing;

use App\Contracts\BillingSystemClient;
use App\Exceptions\PortalException;
use App\Integrations\Billing\DTO\BillData;
use App\Integrations\Billing\DTO\CustomerData;
use App\Integrations\Billing\DTO\QuoteData;
use App\Services\AccountLinking\MockAccountDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MockBillingSystemClient implements BillingSystemClient
{
    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('billing.mode') === 'mock', 503);
    }

    private function rows(): array
    {
        $this->guard();

        return json_decode(file_get_contents(database_path('fixtures/bills.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function verificationContact(string $id): ?array
    {
        $this->guard();

        return (new MockAccountDirectory)->verificationContact($id);
    }

    public function customer(string $accountId): CustomerData
    {
        $rows = array_values(array_filter($this->rows(), fn ($b) => $b['connection']['external_account_id'] === $accountId));
        abort_if(! $rows, 404);
        $balance = 0;
        foreach ($rows as $b) {
            $balance += (int) $this->bill($b['external_bill_id'], $accountId)->data['authoritative_totals']['outstanding_balance_minor'];
        }$b = $rows[0];

        return new CustomerData(['external_customer_id' => $b['customer']['external_customer_id'], 'external_account_id' => $accountId, 'name' => $b['customer']['name'], 'address' => $b['customer']['address'], 'phone' => null, 'currency' => 'BDT', 'outstanding_balance_minor' => (string) $balance, 'source_updated_at' => now()->toIso8601String(), 'customer_type' => $b['connection']['customer_type']]);
    }

    public function bills(array $filters): array
    {
        $rows = [];
        foreach ($this->rows() as $b) {
            $b = $this->bill($b['external_bill_id'])->summary();
            foreach (['external_account_id', 'external_customer_id', 'external_bill_id', 'status'] as $k) {
                if (isset($filters[$k]) && $b[$k] !== $filters[$k]) {
                    continue 2;
                }
            }if (isset($filters['month_from']) && $b['bill_month'] < $filters['month_from']) {
                continue;
            }if (isset($filters['month_to']) && $b['bill_month'] > $filters['month_to']) {
                continue;
            }$rows[] = $b;
        }usort($rows, fn ($a, $b) => [$b['bill_month'], $b['external_bill_id']] <=> [$a['bill_month'], $a['external_bill_id']]);
        $page = $filters['page'] ?? 1;
        $size = $filters['per_page'] ?? 10;

        return ['data' => array_slice($rows, ($page - 1) * $size, $size), 'meta' => ['pagination' => ['page' => $page, 'per_page' => $size, 'total' => count($rows), 'has_next' => $page * $size < count($rows)]]];
    }

    public function bill(string $id, ?string $accountId = null): BillData
    {
        foreach ($this->rows() as $b) {
            if ($b['external_bill_id'] === $id && (! $accountId || $b['connection']['external_account_id'] === $accountId)) {
                $payments = DB::table('mock_billing_payments')->where('bill_id', $id)->get();
                $applied = 0;
                foreach ($payments as $p) {
                    $applied += (int) json_decode($p->payload, true)['verified_amount_minor'];
                }
                $b['authoritative_totals']['paid_amount_minor'] = (string) ((int) $b['authoritative_totals']['paid_amount_minor'] + $applied);
                $b['authoritative_totals']['outstanding_balance_minor'] = (string) max(0, (int) $b['authoritative_totals']['outstanding_balance_minor'] - $applied);
                $b['status'] = $b['authoritative_totals']['outstanding_balance_minor'] === '0' ? 'paid' : $b['status'];
                $b['authoritative_totals']['as_of'] = now()->toIso8601String();
                $b['source_version'] = 'demo-v1-'.count($payments);

                return new BillData($b);
            }
        }abort(404);
    }

    public function quote(string $id, ?string $accountId = null): QuoteData
    {
        $b = $this->bill($id, $accountId)->data;
        $amount = $b['authoritative_totals']['outstanding_balance_minor'];

        return new QuoteData(['quote_id' => (string) Str::uuid(), 'reservation_id' => null, 'external_bill_id' => $id, 'external_account_id' => $b['connection']['external_account_id'], 'external_customer_id' => $b['customer']['external_customer_id'], 'currency' => 'BDT', 'outstanding_balance_minor' => $amount, 'payable_amount_minor' => $amount, 'collection_fee_minor' => '0', 'total_charge_minor' => $amount, 'payment_mode' => 'full_single_bill', 'eligible' => $amount !== '0', 'issued_at' => now()->toIso8601String(), 'expires_at' => now()->addMinutes(5)->toIso8601String(), 'source_version' => $b['source_version'], 'source_updated_at' => $b['source_updated_at']]);
    }

    public function registerPayment(array $p): array
    {
        $this->guard();

        return DB::transaction(function () use ($p) {
            $old = DB::table('mock_billing_payments')->where('reference', $p['external_transaction_reference'])->orWhere('idempotency_key', $p['idempotency_key'])->lockForUpdate()->first();
            if ($old) {
                if (json_decode($old->payload, true) != $p) {
                    throw new PortalException('BILLING_CONFLICT', 409);
                }

return $this->posted($old);
            }
            $b = $this->bill($p['external_bill_id'], $p['external_account_id'])->data;
            if ($b['customer']['external_customer_id'] !== $p['external_customer_id'] || $b['authoritative_totals']['outstanding_balance_minor'] !== $p['verified_amount_minor']) {
                throw new PortalException('BALANCE_CHANGED', 409);
            }
            DB::table('mock_billing_payments')->insert(['id' => (string) Str::uuid(), 'reference' => $p['external_transaction_reference'], 'idempotency_key' => $p['idempotency_key'], 'bill_id' => $p['external_bill_id'], 'payload' => json_encode($p), 'created_at' => now()]);

            return $this->lookupPayment($p['external_transaction_reference']);
        });
    }

    private function posted($row): array
    {
        return [...json_decode($row->payload, true), 'billing_system_payment_id' => $row->id, 'status' => 'posted', 'posted_at' => $row->created_at, 'upstream_receipt_reference' => 'DEMO-RECEIPT-'.$row->id];
    }

    public function lookupPayment(string $r): ?array
    {
        $this->guard();
        $row = DB::table('mock_billing_payments')->where('reference', $r)->first();

        return $row ? $this->posted($row) : null;
    }

    public function capabilities(): array
    {
        $this->guard();

        return ['idempotent_posting' => true, 'reference_lookup' => true, 'atomic_settlement' => true];
    }

    public function health(): array
    {
        $this->guard();

        return ['status' => 'ready', 'mode' => 'mock'];
    }
}
