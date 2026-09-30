<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionService
{
    public function login(Request $request, array $credentials): void
    {
        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }
        $request->session()->regenerate();
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
