<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_billing_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('reference')->unique();
            $t->string('idempotency_key')->unique();
            $t->string('bill_id');
            $t->json('payload');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_billing_payments');
    }
};
