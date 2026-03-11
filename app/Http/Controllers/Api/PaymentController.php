<?php

namespace App\Http\Controllers\Api;

use App\Core\Payments\PaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Gateway;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $service
    ) {}

    // Fonction pour l'initialisation d'un paiement
    public function store(PaymentRequest $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $gateway = Gateway::findOrFail($request->gateway_id);

            // On fusionne les données validées du request avec les infos de l'utilisateur
            $data = array_merge($request->validated(), [
                'email' => $user->email,
                'firstname' => $user->name,
                'lastname' => '',
            ]);

            $transaction = $this->service->initiate($gateway, $data);

            return response()->json([
                'success' => true,
                'data' => $transaction
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function callback(Request $request, $gateway_id)
    {
        try {
            $gateway = Gateway::findOrFail($gateway_id);

            // On récupère le driver pour valider la signature
            $driver = $this->service->resolveDriver($gateway);
            $verifiedData = $driver->validateWebhook($request->all(), $request->headers->all());

            // On cherche la transaction correspondante par l'ID externe
            $transaction = Transaction::where('metadata->external_id', $verifiedData->external_id)->firstOrFail();

            // Si FedaPay confirme que c'est payé
            if ($verifiedData->event === 'transaction.approved') {
                $transaction->update([
                    'status' => 'held',
                    'metadata' => array_merge($transaction->metadata, [
                        'confirmed_at' => now(),
                        // On calcule la date de libération ici
                        'expires_at' => now()->addHours($transaction->escrow_duration ?? 24)
                    ])
                ]);
            }

            return response()->json(['message' => 'Event processed'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
