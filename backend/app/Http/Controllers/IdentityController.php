<?php

namespace App\Http\Controllers;

use App\Models\ExternalAccountLink;
use App\Models\User;
use App\Services\AccountLinking\VerificationService;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class IdentityController extends Controller
{
    public function register(Request $r)
    {
        $r->merge(['email' => strtolower((string) $r->input('email'))]);
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:254|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $data['email'] = strtolower($data['email']);
        $user = User::create($data)->refresh();
        Auth::guard('web')->login($user);
        $r->session()->regenerate();
        Audit::record('user.registered', (string) $user->id);

        return response()->json(['data' => ['id' => (string) $user->id, 'name' => $user->name, 'role' => $user->role]], 201);
    }

    public function resetStart(Request $r, VerificationService $s)
    {
        $d = $r->validate(['email' => 'required|email|max:254']);
        $c = $s->startReset($d['email']);

        return $this->challenge($c);
    }

    public function resetFinish(Request $r, VerificationService $s)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'code' => 'required|digits:6', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $s->reset($d['challenge_id'], $d['code'], $d['password']);

        return response()->json(['data' => null]);
    }

    public function linkStart(Request $r, VerificationService $s)
    {
        $d = $r->validate(['external_account_id' => 'required|string|max:128']);

        return $this->challenge($s->startLink($r->user(), $d['external_account_id']));
    }

    public function linkFinish(Request $r, VerificationService $s)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'code' => 'required|digits:6']);
        $link = $s->verifyLink($r->user(), $d['challenge_id'], $d['code']);

        return response()->json(['data' => $link], 201);
    }

    public function links(Request $r)
    {
        return response()->json(['data' => ExternalAccountLink::where('user_id', $r->user()->id)->where('status', 'verified')->get()]);
    }

    public function unlink(ExternalAccountLink $link)
    {
        Gate::authorize('delete', $link);
        $link->update(['status' => 'revoked', 'revoked_at' => now()]);
        Audit::record('account_link.revoked', (string) $link->id);

        return response()->json(['data' => null]);
    }

    public function preferences(Request $r)
    {
        if ($r->isMethod('PATCH')) {
            $d = $r->validate(['language' => 'required|in:bn,en', 'notifications' => 'required|boolean']);
            $r->user()->forceFill(['preferences' => $d])->save();
        }

return response()->json(['data' => $r->user()->preferences ?? ['language' => 'bn', 'notifications' => false]]);
    }

    private function challenge($c)
    {
        return response()->json(['data' => ['challenge_id' => $c->id, 'expires_at' => $c->expires_at->toIso8601String(), 'message' => 'If the account is eligible, a code will be delivered through its registered channel.']], 202);
    }
}
