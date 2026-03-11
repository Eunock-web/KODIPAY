<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GatewayRequest;

class GatewayController extends Controller
{
    //Fonction pour la configuration du gateway du fournisseur
    public function store(GatewayRequest $request){
        try{
            $validatedData = $request->validated();

            $gateway = $request->user()->gateways()->create($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Passerelle configurée',
                'gateway_id' => $gateway->id
            ]);
        }catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


}
