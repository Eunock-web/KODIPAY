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
});

// Routes pour FedaPay
Route::post('/webhooks/fedapay/{gateway_id}', [PaymentController::class, 'callbackFedaPay'])
    ->name('webhooks.fedapay');
Route::get('/payments/callback/{gateway_id}', [PaymentController::class, 'handleReturn'])
    ->name('payments.callback');

// Webhook KKAPay
Route::get('/webhooks/kkapay/{gateway_id}', [PaymentController::class, 'callbackKKAPay'])
    ->name('webhooks.kkapay');

// Route pour l'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
