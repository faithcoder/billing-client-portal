<?php

namespace App\Integrations\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FakeGateway implements PaymentGateway
{
    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('payments.gateway') === 'fake', 503);
    }

    public function createSession(PaymentAttempt $p): array
    {
        $this->guard();
        DB::table('mock_gateway_transactions')->insertOrIgnore(['id' => (string) Str::uuid(), 'reference' => $p->reference, 'merchant' => config('payments.merchant'), 'amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $tx = DB::table('mock_gateway_transactions')->where('reference', $p->reference)->first();

        return ['transaction_id' => $tx->id, 'checkout_url' => '/fake-checkout/'.$p->id];
    }

    public function verifyNotification(Request $r): array
    {
        $this->guard();
        $signature = hash_hmac('sha256', $r->getContent(), config('app.key'));
        abort_unless(hash_equals($signature, (string) $r->header('X-Fake-Signature')), 401);
        $d = $r->validate(['transaction_id' => 'required|uuid']);

        return $this->fetchTransaction($d['transaction_id']);
    }

    public function fetchTransaction(string $id): array
    {
        $this->guard();
        $tx = DB::table('mock_gateway_transactions')->where('id', $id)->first();
        abort_if(! $tx, 404);

        return $this->evidence($tx);
    }

    public function queryStatus(string $ref): ?array
    {
        $this->guard();
        $tx = DB::table('mock_gateway_transactions')->where('reference', $ref)->first();

        return $tx ? $this->evidence($tx) : null;
    }

    public function simulate(PaymentAttempt $p, string $outcome): array
    {
        $this->guard();
        $tx = DB::table('mock_gateway_transactions')->where('reference', $p->reference)->first();
        abort_if(! $tx, 404);
        if ($tx->status !== 'succeeded') {
            DB::table('mock_gateway_transactions')->where('id', $tx->id)->update(['status' => $outcome, 'verified_at' => $outcome === 'succeeded' ? now() : null, 'updated_at' => now()]);
        }

return $this->fetchTransaction($tx->id);
    }

    private function evidence($t): array
    {
        return ['reference' => $t->reference, 'transaction_id' => $t->id, 'merchant' => $t->merchant, 'amount_minor' => (string) $t->amount_minor, 'currency' => $t->currency, 'status' => $t->status, 'verified_at' => $t->verified_at];
    }
}
