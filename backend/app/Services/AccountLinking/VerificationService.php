<?php

namespace App\Services\AccountLinking;

use App\Contracts\AccountDirectory;
use App\Contracts\NotificationProvider;
use App\Models\ExternalAccountLink;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    public function __construct(private AccountDirectory $directory, private NotificationProvider $notifications) {}

    public function startLink(User $user, string $accountId): VerificationChallenge
    {
        abort_if(ExternalAccountLink::where('user_id', $user->id)->where('status', 'verified')->exists(), 409);
        $contact = $this->directory->verificationContact($accountId);

        // Unknown accounts get indistinguishable challenges which can never succeed.
        return $this->create('link', $user->id, $contact, $contact['phone'] ?? null);
    }

    public function startReset(string $email): VerificationChallenge
    {
        $user = User::where('email', strtolower($email))->first();

        return $this->create('reset', $user?->id, $user ? ['user_id' => $user->id] : null, $user?->email);
    }

    private function create(string $purpose, ?int $userId, ?array $target, ?string $destination): VerificationChallenge
    {
        // Fake OTPs are public synthetic test values ONLY; no production code can select them.
        $fake = app()->environment(['local', 'testing']) && config('portal.notifications') === 'fake';
        abort_unless($fake, 503); // Real delivery is a production integration gate.
        $code = $fake ? '123456' : (string) random_int(100000, 999999);
        $challenge = VerificationChallenge::create(['id' => (string) Str::uuid(), 'user_id' => $userId, 'purpose' => $purpose, 'target' => $target, 'code_hash' => Hash::make($code), 'expires_at' => now()->addSeconds(config('portal.verification_ttl'))]);
        if ($destination) {
            $this->notifications->sendCode($destination, $code, $purpose);
        }

        return $challenge;
    }

    public function verifyLink(User $user, string $id, string $code): ExternalAccountLink
    {
        $result = DB::transaction(function () use ($user, $id, $code) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $c = $this->consume($id, $code, 'link', $user->id);
            if (! $c) {
                return null;
            }
            if (ExternalAccountLink::where('user_id', $user->id)->where('status', 'verified')->exists()) {
                return null;
            }
            if (ExternalAccountLink::where('external_account_id', $c->target['external_account_id'])->where('user_id', '!=', $user->id)->exists()) {
                return null;
            }
            $previous = ExternalAccountLink::where('user_id', $user->id)->first();
            $link = ExternalAccountLink::updateOrCreate(['user_id' => $user->id], ['external_account_id' => $c->target['external_account_id'], 'external_customer_id' => $c->target['external_customer_id'], 'status' => 'verified', 'verified_at' => now(), 'revoked_at' => null]);
            Audit::record('account_link.verified', (string) $link->id, ['previous_account_id' => $previous?->external_account_id, 'external_account_id' => $link->external_account_id, 'external_customer_id' => $link->external_customer_id]);

            return $link;
        });
        if (! $result) {
            $this->invalid();
        }

        return $result;
    }

    public function reset(string $id, string $code, string $password): void
    {
        $ok = DB::transaction(function () use ($id, $code, $password) {
            $c = $this->consume($id, $code, 'reset', null);
            if (! $c) {
                return false;
            }
            $user = User::whereKey($c->target['user_id'])->lockForUpdate()->first();
            if (! $user) {
                return false;
            }
            $user->password = $password;
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record('password.reset', (string) $user->id);

            return true;
        });
        if (! $ok) {
            $this->invalid();
        }
    }

    private function consume(string $id, string $code, string $purpose, ?int $userId): ?VerificationChallenge
    {
        $c = VerificationChallenge::whereKey($id)->lockForUpdate()->first();
        if (! $c || $c->purpose !== $purpose || ($userId !== null && $c->user_id !== $userId) || $c->consumed_at || $c->expires_at->isPast() || $c->attempts >= config('portal.verification_max_attempts')) {
            return null;
        }
        $c->increment('attempts');
        if (! Hash::check($code, $c->code_hash) || ! $c->target) {
            return null;
        }
        $c->consumed_at = now();
        $c->save();

        return $c;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['code' => ['Verification could not be completed. Request a new code if necessary.']]);
    }
}
