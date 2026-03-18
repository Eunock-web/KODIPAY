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
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
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

    public function handleReturn(Request $request, $gateway_id)
    {
        $status = $request->query('status');
        $external_id = $request->query('id');

        \Illuminate\Support\Facades\Log::info('FedaPay redirection (Return URL) reached', [
            'gateway_id' => $gateway_id,
            'status' => $status,
            'external_id' => $external_id
        ]);

        //  Trouver la transaction locale correspondante
        $transaction = Transaction::where('metadata->external_id', $external_id)->first();

        if ($transaction) {
            //  Déclencher une réconciliation manuelle immédiate
            $this->service->reconcile($transaction);

            //  Rediriger vers l'URL de retour du client SI elle existe
            if ($transaction->callback_url) {
                // On ajoute les paramètres à l'URL de retour pour que le client sache le résultat
                $separator = str_contains($transaction->callback_url, '?') ? '&' : '?';
                $redirectUrl = $transaction->callback_url . $separator . "id={$transaction->id}&status={$transaction->status}";

                return redirect($redirectUrl);
            }
        }

        return response()->json([
            'message' => 'Transition terminée',
            'status' => $transaction ? $transaction->status : $status,
            'external_id' => $external_id,
            'internal_id' => $transaction ? $transaction->id : null
        ]);
    }

    public function callback(Request $request, $gateway_id)
    {
        // Cette méthode semble être une ancienne version,
        // on redirige vers la méthode processWebhook universelle
        return $this->processWebhook($request, $gateway_id);
    }

    // Pour FedaPay
    public function callbackFedaPay(Request $request, $gateway_id)
    {
        return $this->processWebhook($request, $gateway_id);
    }

    // Pour KKAPay
    public function callbackKKAPay(Request $request, $gateway_id)
    {
        return $this->processWebhook($request, $gateway_id);
    }

    /**
     * La méthode privée qui fait tout le travail "universel"
     */
    private function processWebhook(Request $request, $gateway_id)
    {
        try {
            $gateway = Gateway::findOrFail($gateway_id);
            $driver = $this->service->resolveDriver($gateway);

            // Le driver traduit le langage spécifique du prestataire en langage KODIPAY
            $verifiedData = $driver->validateWebhook($request->all(), $request->headers->all());

            $transaction = Transaction::where('metadata->external_id', $verifiedData->external_id)->firstOrFail();

            if (in_array($verifiedData->event, ['transaction.approved', 'transaction.transferred']) && $transaction->status === 'pending') {
                $transaction->update([
                    'status' => 'held',
                    'metadata' => array_merge($transaction->metadata, [
                        'confirmed_at' => now(),
                        'expires_at' => now()->addHours($transaction->escrow_duration ?? 24)
                    ])
                ]);
            }

            return response()->json(['status' => 'processed', 'message' => 'Event processed']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Webhook processing failed', [
                'gateway_id' => $gateway_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => $e->getMessage()
            ], 400);  // Retourner 400 comme attendu par les tests en cas d'erreur de validation/not found
        }
    }
}
