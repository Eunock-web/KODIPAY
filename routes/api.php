<?php

use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::middleware('auth:sanctum')->group(function () {
    //Route pour la configuration des clées API(fedapay et etc)
    Route::post('/gateways', [GatewayController::class, 'store']);

    //Route pour initier un paiement
    Route::post('/payments', [PaymentController::class, 'store']);
});

//Route pour les webhooks 
Route::post('/webhooks/fedapay/{gateway_id}', [PaymentController::class, 'callback'])
    ->name('webhooks.fedapay');
