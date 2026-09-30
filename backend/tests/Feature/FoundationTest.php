<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_and_request_context(): void
    {
        $response = $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('data.mode', 'mock');
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_errors_are_safe_and_consistent(): void
    {
        $response = $this->getJson('/api/v1/missing')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.request_id'));
        Route::get('/api/v1/test-failure', fn () => throw new \RuntimeException('secret upstream credential'));
        $this->getJson('/api/v1/test-failure')->assertStatus(500)->assertJsonPath('error.code', 'SERVER_ERROR')->assertDontSee('secret upstream credential')->assertDontSee('trace');
    }

    public function test_live_and_production_mock_fail_closed(): void
    {
        config(['billing.mode' => 'live', 'billing.url' => 'https://billing.invalid', 'billing.token' => 'test-only']);
        $this->getJson('/api/v1/status')->assertStatus(503);
        config(['billing.mode' => null]);
        $this->getJson('/api/v1/status')->assertStatus(503);
        config(['billing.mode' => 'mock']);
        $this->app->instance('env', 'production');
        $this->getJson('/api/v1/status')->assertStatus(503);
    }

    public function test_session_login_logout_and_admin_authorization(): void
    {
        $user = User::factory()->create(['password' => 'a-local-test-password']);
        $this->getJson('/api/v1/session')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'a-local-test-password'])->assertOk()->assertJsonPath('data.id', (string) $user->id);
        $this->getJson('/api/v1/admin/status')->assertForbidden();
        $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('data.role', 'client');
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertGuest('web');
        Auth::forgetGuards();
        $this->getJson('/api/v1/session')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/v1/admin/status')->assertOk();
    }

    public function test_csrf_rejects_login_without_a_token_outside_test_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.invalid', 'password' => 'test'])->assertStatus(419)->assertJsonPath('error.code', 'CSRF_EXPIRED');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.invalid', 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.invalid', 'password' => 'wrong'])->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED')->assertHeader('Retry-After');
    }
}
