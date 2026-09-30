<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentOutbox extends Model
{
    protected $table = 'payment_outbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'ambiguous' => 'boolean', 'available_at' => 'immutable_datetime', 'lease_until' => 'immutable_datetime'];
    }
}
