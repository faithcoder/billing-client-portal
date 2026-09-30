<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Exceptions\PortalException;
use App\Integrations\Billing\DTO\QuoteData;
use App\Integrations\Billing\MockBillingSystemClient;
use App\Integrations\Gateways\FakeGateway;
use App\Models\ExternalAccountLink;
use App\Models\PaymentAttempt;
use App\Models\PaymentOutbox;
use App\Models\User;
use App\Services\Billing\AccountAccess;
use App\Services\Payments\InitiatePayment;
use App\Services\Payments\PaymentFinalizer;
use App\Services\Payments\SyncService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::factory()->create();
        ExternalAccountLink::create(['user_id' => $u->id, 'external_account_id' => '000007', 'external_customer_id' => '000042', 'verified_at' => now(), 'status' => 'verified']);

        return $u;
    }

    private function payment(): PaymentAttempt
    {
        Queue::fake();

        return app(InitiatePayment::class)->execute($this->user(), '000007-2026-08');
    }

    private function success(PaymentAttempt $p): array
    {
        return app(FakeGateway::class)->simulate($p, 'succeeded');
    }

    public function test_amount_tampering_is_ignored_and_attempts_are_reused(): void
    {
        Queue::fake();
        $u = $this->user();
        $a = $this->actingAs($u)->postJson('/api/v1/bills/000007-2026-08/payments', ['amount_minor' => '1', 'customer_id' => '000043'])->assertOk()->assertJsonPath('data.amount_minor', '26300')->json('data.id');
        $b = $this->postJson('/api/v1/bills/000007-2026-08/payments')->assertOk()->json('data.id');
        $this->assertSame($a, $b);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('mock_gateway_transactions', 1);
        $this->postJson('/api/v1/bills/000008-2026-08/payments')->assertNotFound();
    }

    public function test_duplicate_and_out_of_order_callbacks_preserve_success_and_one_receipt(): void
    {
        $p = $this->payment();
        $e = $this->success($p);
        $f = app(PaymentFinalizer::class);
        $f->finalize($e);
        $f->finalize($e);
        $f->finalize([...$e, 'status' => 'cancelled']);
        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertSame('pending', $p->fresh()->sync_status);
        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertDatabaseCount('payment_outbox', 1);
    }

    public function test_mismatched_amount_and_unsigned_callback_do_not_settle(): void
    {
        $p = $this->payment();
        $e = $this->success($p);
        app(PaymentFinalizer::class)->finalize([...$e, 'amount_minor' => '1']);
        $this->assertNotSame('succeeded', $p->fresh()->status);
        $this->assertSame('needs_review', $p->fresh()->sync_status);
        $this->assertDatabaseCount('payment_receipts', 0);
        $this->postJson('/api/v1/gateway/notifications', ['transaction_id' => $e['transaction_id']])->assertUnauthorized();
    }

    public function test_late_success_after_cancel_and_new_attempt_requires_review(): void
    {
        $p = $this->payment();
        $gateway = app(FakeGateway::class);
        $f = app(PaymentFinalizer::class);
        $f->finalize($gateway->simulate($p, 'cancelled'));
        $next = app(InitiatePayment::class)->execute(User::find($p->user_id), $p->external_bill_id);
        $this->assertNotSame($p->id, $next->id);
        $f->finalize($this->success($p));
        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertSame('needs_review', $p->fresh()->sync_status);
        $this->assertSame('POSSIBLE_DUPLICATE_COLLECTION', $p->fresh()->review_reason);
    }

    public function test_lost_response_after_upstream_commit_is_resolved_by_reference(): void
    {
        $p = $this->payment();
        app(PaymentFinalizer::class)->finalize($this->success($p));
        $billing = new class extends MockBillingSystemClient
        {
            public int $posts = 0;

            public function registerPayment(array $p): array
            {
                $this->posts++;
                parent::registerPayment($p);
                throw new PortalException('WRITE_RESULT_UNKNOWN');
            }
        };
        (new SyncService($billing))->run($p->id);
        $this->assertSame('synced', $p->fresh()->sync_status);
        $this->assertSame(1, $billing->posts);
        $this->assertDatabaseCount('mock_billing_payments', 1);
        (new SyncService($billing))->run($p->id);
        $this->assertSame(1, $billing->posts);
    }

    public function test_billing_outage_keeps_verified_receipt_and_safe_retry(): void
    {
        $p = $this->payment();
        app(PaymentFinalizer::class)->finalize($this->success($p));
        $billing = new class extends MockBillingSystemClient
        {
            public function lookupPayment(string $r): ?array
            {
                throw new PortalException('BILLING_UNAVAILABLE');
            }
        };
        (new SyncService($billing))->run($p->id);
        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertSame('failed', $p->fresh()->sync_status);
        $this->assertDatabaseCount('payment_receipts', 1);
        PaymentOutbox::where('payment_id', $p->id)->update(['available_at' => now()]);
        app(SyncService::class)->run($p->id);
        $this->assertSame('synced', $p->fresh()->sync_status);
    }

    public function test_receipt_is_private_and_financial_evidence_immutable(): void
    {
        $p = $this->payment();
        app(PaymentFinalizer::class)->finalize($this->success($p));
        $before = DB::table('payment_receipts')->value('snapshot');
        app(SyncService::class)->run($p->id);
        $this->assertSame($before, DB::table('payment_receipts')->value('snapshot'));
        $this->actingAs(User::factory()->create())->getJson('/api/v1/payments/'.$p->id.'/receipt')->assertForbidden();
        $this->actingAs(User::find($p->user_id))->getJson('/api/v1/payments/'.$p->id.'/receipt')->assertOk();
    }

    public function test_active_key_database_constraint_blocks_competing_attempts(): void
    {
        $p = $this->payment();
        $attributes = $p->getAttributes();
        $attributes['id'] = (string) Str::uuid();
        $attributes['reference'] = 'competing';
        $attributes['idempotency_key'] = 'competing';
        $attributes['gateway_transaction_id'] = null;
        $this->expectException(QueryException::class);
        DB::table('payment_attempts')->insert($attributes);
    }

    public function test_expired_quote_prevents_session_creation(): void
    {
        $u = $this->user();
        $billing = new class extends MockBillingSystemClient
        {
            public function quote(string $id, ?string $accountId = null): QuoteData
            {
                $q = parent::quote($id, $accountId)->data;
                $q['expires_at'] = now()->subMinute()->toIso8601String();

                return new QuoteData($q);
            }
        };
        $s = new InitiatePayment($billing, app(PaymentGateway::class), app(AccountAccess::class));
        try {
            $s->execute($u, '000007-2026-08');
            $this->fail();
        } catch (PortalException $e) {
            $this->assertSame('QUOTE_NOT_PAYABLE', $e->errorCode);
        }$this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_worker_recovery_never_reposts_uncertain_non_idempotent_write(): void
    {
        $p = $this->payment();
        app(PaymentFinalizer::class)->finalize($this->success($p));
        PaymentOutbox::where('payment_id', $p->id)->update(['ambiguous' => true, 'lease_until' => now()->subMinute()]);
        $billing = new class extends MockBillingSystemClient
        {
            public int $posts = 0;

            public function capabilities(): array
            {
                return ['reference_lookup' => false, 'idempotent_posting' => false, 'atomic_settlement' => false];
            }

            public function registerPayment(array $p): array
            {
                $this->posts++;

                return parent::registerPayment($p);
            }
        };
        (new SyncService($billing))->run($p->id);
        $this->assertSame(0, $billing->posts);
        $this->assertSame('needs_review', $p->fresh()->sync_status);
        $this->assertSame('succeeded', $p->fresh()->status);
    }

    public function test_changed_amount_between_bill_and_quote_prevents_session(): void
    {
        Queue::fake();
        $billing = new class extends MockBillingSystemClient
        {
            public function quote(string $id, ?string $accountId = null): QuoteData
            {
                $q = parent::quote($id, $accountId)->data;
                $q['payable_amount_minor'] = '28000';
                $q['outstanding_balance_minor'] = '28000';
                $q['total_charge_minor'] = '28000';

                return new QuoteData($q);
            }
        };
        $service = new InitiatePayment($billing, app(PaymentGateway::class), app(AccountAccess::class));
        try {
            $service->execute($this->user(), '000007-2026-08');
            $this->fail();
        } catch (PortalException $e) {
            $this->assertSame('QUOTE_NOT_PAYABLE', $e->errorCode);
        }
        $this->assertDatabaseCount('payment_attempts', 0);
    }
}
