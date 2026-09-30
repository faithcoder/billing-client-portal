<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;

class PaymentEvents
{
    public static function add(string $payment, string $event, array $metadata = []): void
    {
        DB::table('payment_events')->insert(['payment_id' => $payment, 'event' => $event, 'metadata' => json_encode($metadata), 'created_at' => now()]);
    }
}
