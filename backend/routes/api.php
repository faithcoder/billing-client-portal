<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::prefix('v1')->group(function () {
    Route::get('/status', StatusController::class);
    Route::get('/session', [SessionController::class, 'show'])->middleware('auth:sanctum');
    Route::get('/admin/status', StatusController::class)->middleware(['auth:sanctum', 'can:access-admin']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/account-links', [IdentityController::class, 'links']);
    Route::get('/preferences', [IdentityController::class, 'preferences']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/profile', [BillingController::class, 'customer']);
    Route::get('/bills', [BillingController::class, 'index']);
    Route::get('/bills/{bill}', [BillingController::class, 'show']);
    Route::get('/admin/bills', [BillingController::class, 'adminIndex']);
    Route::get('/admin/bills/{bill}', [BillingController::class, 'adminShow']);
    Route::get('/admin/integration-health', [BillingController::class, 'health']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt']);
});
Route::post('/v1/gateway/notifications', [PaymentController::class, 'callback'])->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/service-requests', [ServiceRequestController::class, 'index']);
    Route::get('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'show']);
    Route::get('/attachments/{attachment}', [ServiceRequestController::class, 'download']);
    Route::get('/exports/payments', [AdminController::class, 'export']);
    Route::get('/admin/summary', [AdminController::class, 'summary']);
    Route::get('/admin/exports/payments', [AdminController::class, 'export']);
    Route::get('/admin/payments/{payment}/events', [AdminController::class, 'events']);
    Route::get('/admin/data/{module}', [AdminController::class, 'listing']);
});
