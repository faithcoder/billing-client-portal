<?php

namespace App\Contracts;

interface AccountDirectory
{
    /** Trusted lookup for verification only. Never expose this result before proof. */
    public function verificationContact(string $accountId): ?array;
}
