<?php

namespace App\Contracts;

use App\Integrations\Billing\DTO\BillData;
use App\Integrations\Billing\DTO\CustomerData;
use App\Integrations\Billing\DTO\QuoteData;

interface BillingSystemClient extends AccountDirectory
{
    public function customer(string $accountId): CustomerData;

    public function bills(array $filters): array;

    public function bill(string $billId, ?string $accountId = null): BillData;

    public function quote(string $billId, ?string $accountId = null): QuoteData;

    public function registerPayment(array $payload): array;

    public function lookupPayment(string $reference): ?array;

    public function capabilities(): array;

    public function health(): array;
}
