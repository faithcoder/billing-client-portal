<?php

namespace Tests\Feature;

use App\Models\ExternalAccountLink;
use App\Models\User;
use App\Services\AccountLinking\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_accept_privileged_role(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Synthetic user', 'email' => 'new@example.invalid', 'password' => 'LocalTestPassword123', 'password_confirmation' => 'LocalTestPassword123', 'role' => 'admin'])->assertCreated()->assertJsonPath('data.role', 'client');
    }

    public function test_only_verified_registered_channel_links_an_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->getJson('/api/v1/account-links')->assertUnauthorized();
        $id = $this->actingAs($user)->postJson('/api/v1/account-links/challenges', ['external_account_id' => '000007', 'phone' => '+8801111111111'])->assertStatus(202)->json('data.challenge_id');
        $this->assertDatabaseCount('external_account_links', 0);
        $this->actingAs($other)->postJson('/api/v1/account-links/verify', ['challenge_id' => $id, 'code' => '123456'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/v1/account-links/verify', ['challenge_id' => $id, 'code' => '000000'])->assertUnprocessable();
        $this->postJson('/api/v1/account-links/verify', ['challenge_id' => $id, 'code' => '123456'])->assertCreated()->assertJsonPath('data.external_account_id', '000007');
        $this->postJson('/api/v1/account-links/challenges', ['external_account_id' => '000008'])->assertConflict();
        $link = ExternalAccountLink::first();
        $this->actingAs($other)->deleteJson('/api/v1/account-links/'.$link->id)->assertForbidden();
    }

    public function test_unknown_account_is_indistinguishable_but_cannot_verify(): void
    {
        $user = User::factory()->create();
        $s = app(VerificationService::class);
        $challenge = $s->startLink($user, 'unknown');
        $this->actingAs($user)->postJson('/api/v1/account-links/verify', ['challenge_id' => $challenge->id, 'code' => '123456'])->assertUnprocessable();
        $this->assertDatabaseCount('external_account_links', 0);
    }

    public function test_expiry_and_attempt_budget(): void
    {
        $user = User::factory()->create();
        $c = app(VerificationService::class)->startLink($user, '000007');
        $this->travel(6)->minutes();
        $this->actingAs($user)->postJson('/api/v1/account-links/verify', ['challenge_id' => $c->id, 'code' => '123456'])->assertUnprocessable();
        $this->travelBack();
        $c = app(VerificationService::class)->startLink($user, '000007');
        $c->update(['attempts' => 5]);
        $this->postJson('/api/v1/account-links/verify', ['challenge_id' => $c->id, 'code' => '123456'])->assertUnprocessable();
    }

    public function test_verification_rate_limit_and_production_fake_guard(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/account-links/challenges', ['external_account_id' => 'unknown'])->assertStatus(202);
        }
        $this->postJson('/api/v1/account-links/challenges', ['external_account_id' => 'unknown'])->assertStatus(429);
    }

    public function test_password_reset_is_single_use(): void
    {
        $user = User::factory()->create();
        $c = app(VerificationService::class)->startReset($user->email);
        $body = ['challenge_id' => $c->id, 'code' => '123456', 'password' => 'ReplacementPassword123', 'password_confirmation' => 'ReplacementPassword123'];
        $this->postJson('/api/v1/auth/reset/finish', $body)->assertOk();
        $this->postJson('/api/v1/auth/reset/finish', $body)->assertUnprocessable();
        $this->assertTrue(Hash::check('ReplacementPassword123',$user->fresh()->password));
    }
}
