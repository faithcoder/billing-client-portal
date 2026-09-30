<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

// Web middleware guarantees sessions and CSRF even without a stateful Origin header.
Route::post('/api/v1/auth/login', [SessionController::class, 'store'])->middleware('throttle:login');
Route::post('/api/v1/auth/logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum');

Route::post('/api/v1/auth/register', [IdentityController::class, 'register'])->middleware('throttle:registration');
Route::post('/api/v1/auth/reset/start', [IdentityController::class, 'resetStart'])->middleware('throttle:verification');
Route::post('/api/v1/auth/reset/finish', [IdentityController::class, 'resetFinish'])->middleware('throttle:verification');
Route::middleware('auth:sanctum')->prefix('api/v1')->group(function () {
    Route::post('/account-links/challenges', [IdentityController::class, 'linkStart'])->middleware('throttle:verification');
    Route::post('/account-links/verify', [IdentityController::class, 'linkFinish'])->middleware('throttle:verification');
    Route::delete('/account-links/{link}', [IdentityController::class, 'unlink']);
    Route::patch('/preferences', [IdentityController::class, 'preferences']);
});

Route::post('/api/v1/bills/{bill}/quote', [BillingController::class, 'quote'])->middleware(['auth:sanctum', 'throttle:verification']);

Route::middleware(['auth:sanctum', 'throttle:verification'])->prefix('api/v1')->group(function () {
    Route::post('/bills/{bill}/payments', [PaymentController::class, 'initiate']);
    Route::post('/dev/gateway/{payment}/complete', [PaymentController::class, 'simulate']);
});

Route::middleware('auth:sanctum')->prefix('api/v1')->group(function () {
    Route::post('/service-requests', [ServiceRequestController::class, 'store']);
    Route::patch('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'update']);
    Route::post('/service-requests/{serviceRequest}/submit', [ServiceRequestController::class, 'submit']);
    Route::post('/service-requests/{serviceRequest}/replies', [ServiceRequestController::class, 'reply']);
    Route::post('/service-requests/{serviceRequest}/attachments', [ServiceRequestController::class, 'upload'])->middleware('throttle:verification');
    Route::post('/admin/service-requests/{serviceRequest}/review', [ServiceRequestController::class, 'review']);
    Route::patch('/admin/users/{user}/role', [AdminController::class, 'role']);
    Route::post('/admin/account-links/{link}/revoke', [AdminController::class, 'revoke']);
    Route::post('/admin/payments/{payment}/retry', [AdminController::class, 'retry']);
    Route::post('/admin/payments/{payment}/reconcile', [AdminController::class, 'reconcile']);
});
