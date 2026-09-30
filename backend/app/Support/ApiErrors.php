<?php

namespace App\Support;

use App\Exceptions\PortalException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiErrors
{
    public static function render(Response $response, Throwable $exception, Request $request): Response
    {
        if (! $request->is('api/*', 'sanctum/*')) {
            return $response;
        }
        $status = $response->getStatusCode();
        $code = $exception instanceof PortalException ? $exception->errorCode : match ($status) {
            401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED', 419 => 'CSRF_EXPIRED', 422 => 'VALIDATION_FAILED',
            429 => 'RATE_LIMITED', 503 => 'SERVICE_UNAVAILABLE', default => 'SERVER_ERROR',
        };
        $id = $request->attributes->get('request_id') ?? (string) Str::uuid();
        $headers = ['X-Request-ID' => $id, 'Cache-Control' => 'private, no-store'];
        foreach (['Retry-After', 'Allow'] as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return response()->json(['error' => [
            'code' => $code, 'message_key' => 'errors.'.strtolower($code),
            'request_id' => $id, 'retryable' => in_array($status, [429, 502, 503, 504]),
            // Validation messages contain field errors only, never input or exception traces.
            'details' => $exception instanceof ValidationException ? $exception->errors() : (object) [],
        ]], $status, $headers);
    }
}
