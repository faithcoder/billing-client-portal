<?php

namespace App\Services\AccountLinking;

use App\Contracts\AccountDirectory;

class MockAccountDirectory implements AccountDirectory
{
    public function verificationContact(string $accountId): ?array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('billing.mode') === 'mock', 503);

        return match ($accountId) {
            '000007' => ['external_account_id' => '000007', 'external_customer_id' => '000042', 'phone' => '+8800000000007'],'000008' => ['external_account_id' => '000008', 'external_customer_id' => '000043', 'phone' => '+8800000000008'], default => null
        };
    }
}
