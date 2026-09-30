<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'target'];

    protected function casts(): array
    {
        return ['target' => 'encrypted:array', 'expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }
}
