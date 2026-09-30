<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalAccountLink extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
