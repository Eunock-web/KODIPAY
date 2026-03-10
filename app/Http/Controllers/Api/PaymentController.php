<?php

namespace App\Http\Controllers\Api;

use App\Core\Payments\PaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Gateway;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $service
    ) {}

    public function store(PaymentRequest $request){
        try {
                $gateway = Gateway::findOrFail($request->gateway_id);
                $transaction = $this->service->initiate($gateway, $request->validated());

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
