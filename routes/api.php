<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Route pour la configuration des clées API(fedapay et etc)
    Route::post('/gateways', [GatewayController::class, 'store']);

    // Route pour initier un paiement
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/payments/redirect', [PaymentController::class, 'storeRedirect']);
    Route::post('/payments/direct', [PaymentController::class, 'storeDirect']);
});

// Routes pour FedaPay
Route::post('/payments/callback/{gateway_id}', [PaymentController::class, 'callback'])->name('payments.callback');
Route::get('/payments/return/{gateway_id}', [PaymentController::class, 'handleReturn'])->name('payments.return');

// Generic Webhook Endpoints (without gateway_id in URL)
Route::post('/webhooks/fedapay', [PaymentController::class, 'fedapayWebhook'])->name('webhooks.fedapay');
Route::post('/webhooks/kkapay', [PaymentController::class, 'kkapayWebhook'])->name('webhooks.kkapay');

// Webhook KKAPay
Route::post('/webhooks/kkapay/{gateway_id}', [PaymentController::class, 'callbackKKAPay'])
    ->name('webhooks.kkapay');

// Route pour l'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
