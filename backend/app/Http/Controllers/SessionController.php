<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\Auth\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function store(LoginRequest $request, SessionService $sessions): JsonResponse
    {
        $sessions->login($request, $request->validated());

        return $this->show($request);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => ['id' => (string) $user->id, 'name' => $user->name, 'role' => $user->role]]);
    }

    public function destroy(Request $request, SessionService $sessions): JsonResponse
    {
        $sessions->logout($request);

        return response()->json(['data' => null]);
    }
}
