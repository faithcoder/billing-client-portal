<?php

namespace Tests\Feature;

use App\Exceptions\PortalException;
use App\Integrations\Billing\HttpBillingSystemClient;
use App\Models\ExternalAccountLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function live(): HttpBillingSystemClient
    {
        config(['billing.mode' => 'live', 'billing.url' => 'https://billing.example.test', 'billing.allowed_hosts' => ['billing.example.test'], 'billing.token' => 'synthetic', 'billing.contract_approved' => true]);

        return new HttpBillingSystemClient;
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(database_path('fixtures/bills.json')), true)[0];
    }

    public function test_leading_zero_mapping_and_server_pagination(): void
    {
        $client = $this->live();
        Http::fake(['*' => Http::response(['data' => [$this->fixture()], 'meta' => ['pagination' => ['page' => 2, 'per_page' => 1, 'total' => 20043, 'has_next' => true]]])]);
        $result = $client->bills(['external_account_id' => '000007', 'page' => 2, 'per_page' => 1]);
        $this->assertSame('000007', $result['data'][0]['external_account_id']);
        $this->assertSame(20043, $result['meta']['pagination']['total']);
        Http::assertSent(fn ($r) => $r['page'] === 2 && $r['per_page'] === 1 && $r->hasHeader('Authorization', 'Bearer synthetic'));
        Http::assertSentCount(1);
    }

    public function test_malformed_payload_rejected(): void
    {
        $c = $this->live();
        $b = $this->fixture();
        $b['authoritative_totals']['outstanding_balance_minor'] = 26300;
        Http::fake(['*' => Http::response(['data' => $b])]);
        $this->expectException(PortalException::class);
        $c->bill($b['external_bill_id'], '000007');
    }

    public function test_timeout_reads_are_bounded_and_auth_failures_not_retried(): void
    {
        $c = $this->live();
        Http::fake(['*' => Http::failedConnection()]);
        try {
            $c->bill('x');
            $this->fail();
        } catch (PortalException $e) {
            $this->assertSame('BILLING_UNAVAILABLE', $e->errorCode);
        }Http::assertSentCount(3);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 401)]);
        try {
            $c->bill('x');
            $this->fail();
        } catch (PortalException $e) {
            $this->assertSame('BILLING_AUTH_FAILED', $e->errorCode);
        }Http::assertSentCount(1);
    }

    public function test_unapproved_host_never_receives_request(): void
    {
        $c = $this->live();
        config(['billing.url' => 'http://127.0.0.1']);
        Http::fake();
        try {
            $c->health();
            $this->fail();
        } catch (PortalException $e) {
            $this->assertSame('BILLING_NOT_CONFIGURED', $e->errorCode);
        }Http::assertNothingSent();
    }

    public function test_writes_are_never_blindly_retried(): void
    {
        $c = $this->live();
        Http::fake(['*' => Http::failedConnection()]);
        try {
            $c->registerPayment(['idempotency_key' => 'test']);
            $this->fail();
        } catch (PortalException $e) {
            $this->assertSame('WRITE_RESULT_UNKNOWN', $e->errorCode);
        }Http::assertSentCount(1);
    }

    public function test_client_search_and_detail_are_scoped_to_verified_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/bills')->assertNotFound();
        ExternalAccountLink::create(['user_id' => $user->id, 'external_account_id' => '000007', 'external_customer_id' => '000042', 'verified_at' => now(), 'status' => 'verified']);
        $this->getJson('/api/v1/bills?external_account_id=000008')->assertNotFound();
        $this->getJson('/api/v1/bills/000008-2026-08')->assertNotFound();
        $this->getJson('/api/v1/bills?per_page=1&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 3);
        $this->getJson('/api/v1/admin/bills')->assertForbidden();
    }
}
