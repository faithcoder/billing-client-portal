<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 16)->default('client');
            $t->json('preferences')->nullable();
        });
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
        Schema::create('external_account_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->string('external_account_id', 128)->unique();
            $t->string('external_customer_id', 128);
            $t->string('status', 16)->default('verified');
            $t->timestamp('verified_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('verification_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('purpose', 16);
            $t->text('target')->nullable();
            $t->string('code_hash');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('event', 100)->index();
            $t->string('subject', 160)->nullable();
            $t->json('metadata')->nullable();
            $t->uuid('request_id')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('verification_challenges');
        Schema::dropIfExists('external_account_links');
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
            $t->dropColumn(['role', 'preferences']);
        });
    }
};
