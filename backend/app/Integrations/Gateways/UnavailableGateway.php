<?php

namespace App\Integrations\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

class UnavailableGateway implements PaymentGateway
{
    public function createSession(PaymentAttempt $p): array
    {
        abort(503);
    }

    public function verifyNotification(Request $r): array
    {
        abort(503);
    }

    public function fetchTransaction(string $id): array
    {
        abort(503);
    }

    public function queryStatus(string $r): ?array
    {
        abort(503);
    }
}
