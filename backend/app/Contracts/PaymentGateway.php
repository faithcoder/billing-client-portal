<?php

namespace App\Contracts;

use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createSession(PaymentAttempt $payment): array;

    public function verifyNotification(Request $request): array;

    public function fetchTransaction(string $transactionId): array;

    public function queryStatus(string $reference): ?array;
}
