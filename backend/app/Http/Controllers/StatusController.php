<?php

namespace App\Http\Controllers;

use App\Services\Billing\IntegrationReadiness;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __invoke(IntegrationReadiness $readiness): JsonResponse
    {
        abort_unless($readiness->available(), 503);

        return response()->json(['data' => ['status' => 'ready', 'mode' => 'mock', 'phase' => 9]]);
    }
}
