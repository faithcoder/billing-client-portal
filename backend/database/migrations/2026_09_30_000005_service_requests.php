<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->string('reference')->unique();
            $t->string('kind', 20)->index();
            $t->string('category', 32)->nullable();
            $t->string('external_account_id', 128)->nullable();
            $t->string('external_bill_id', 128)->nullable();
            $t->string('subject', 180);
            $t->text('description')->nullable();
            $t->json('form_data')->nullable();
            $t->string('status', 32)->default('draft')->index();
            $t->timestamps();
        });
        Schema::create('service_request_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('service_request_id');
            $t->foreign('service_request_id')->references('id')->on('service_requests');
            $t->foreignId('actor_id')->constrained('users');
            $t->string('status', 32);
            $t->text('message')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('attachments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('service_request_id');
            $t->foreign('service_request_id')->references('id')->on('service_requests');
            $t->string('path');
            $t->string('original_name', 160);
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->string('scan_status', 32);
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('service_request_events');
        Schema::dropIfExists('service_requests');
    }
};
