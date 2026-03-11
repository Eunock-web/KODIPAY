<?php

namespace App\Http\Controllers\Api;

use App\Core\Payments\PaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Gateway;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $service
    ) {}

    //Fonction pour l'initialisation d'un paiement
    public function store(PaymentRequest $request){
        try {
                $user = Auth::user();
                if (!$user) {
                    return response()->json(['error' => 'Unauthenticated'], 401);
                }
                $gateway = Gateway::findOrFail($request->gateway_id);
                $transaction = $this->service->initiate($gateway, $user->toArray());

                return response()->json([
                    'success' => true,
                    'data' => $transaction
                ], 201);
            return response()->json(['error' => 'gateway_id is required'], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function callback(){

    }
}
