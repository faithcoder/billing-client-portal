<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->string('external_bill_id', 128)->index();
            $t->string('external_account_id', 128);
            $t->string('external_customer_id', 128);
            $t->string('reference', 100)->unique();
            $t->string('idempotency_key', 100)->unique();
            $t->string('active_key', 64)->nullable()->unique();
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('status', 20)->default('initiated')->index();
            $t->string('sync_status', 20)->default('not_required')->index();
            $t->string('gateway', 32);
            $t->string('gateway_transaction_id', 128)->nullable();
            $t->unique(['gateway', 'gateway_transaction_id']);
            $t->text('checkout_url')->nullable();
            $t->json('quote');
            $t->json('bill_snapshot');
            $t->timestamp('verified_at')->nullable();
            $t->string('billing_system_payment_id', 128)->nullable();
            $t->string('upstream_receipt_reference', 160)->nullable();
            $t->string('review_reason', 100)->nullable();
            $t->timestamps();
        });
        Schema::create('payment_outbox', function (Blueprint $t) {
            $t->id();
            $t->uuid('payment_id')->unique();
            $t->foreign('payment_id')->references('id')->on('payment_attempts');
            $t->json('payload');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->boolean('ambiguous')->default(false);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_receipts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('payment_id')->unique();
            $t->foreign('payment_id')->references('id')->on('payment_attempts');
            $t->string('reference', 100)->unique();
            $t->json('snapshot');
            $t->timestamp('created_at');
        });
        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('payment_id')->index();
            $t->string('event', 80);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('mock_gateway_transactions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('reference')->unique();
            $t->string('merchant');
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('status', 20);
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['mock_gateway_transactions', 'payment_events', 'payment_receipts', 'payment_outbox', 'payment_attempts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
