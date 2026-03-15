<?php
namespace App\Core\Payments;

use App\Core\Payments\Contract\PaymentsGatewayInterface;
use App\Gateways\Fedapay\FedapayDriver;
use App\Models\Gateway;
use App\Models\Transaction;
use Exception;

class PaymentService
{
    /**
     * Cette méthode doit retourner le bon Driver selon le type stocké en base
     */
    public function resolveDriver(Gateway $gateway): PaymentsGatewayInterface
    {
        if ($gateway->gateway_type == 'fedapay') {
            return new FedapayDriver($gateway->api_key, $gateway->is_live, $gateway->settings ?? []);
        }

        if ($gateway->gateway_type == 'kkapay') {
            return new \App\Gateways\Kkapay\KkapayDriver(
                $gateway->api_key,
                $gateway->is_live,
                $gateway->settings['public_key'] ?? null
            );
        }

        throw new Exception('Le driver que vous fournissez est indisponible');
    }

    public function initiate(Gateway $gateway, array $data): Transaction
    {
        $driver = $this->resolveDriver($gateway);

        // On demande au driver de créer le paiement chez le prestataire
        $paymentInfo = $driver->makePayment($data['amount'], $data['currency'], $data);

        // On enregistre la transaction dans NOTRE base de données Kodipay
        return Transaction::create([
            'gateway_id' => $gateway->id,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => 'pending',
            'escrow_duration' => $data['escrow_duration'] ?? null,
            'callback_url' => $data['callback_url'] ?? null,  // Sauvegarde de l'URL de retour client
            'metadata' => [
                'external_id' => $paymentInfo->external_id,
                'payment_url' => $paymentInfo->url,
                'payment_token' => $paymentInfo->token,
                'payout_destination' => $data['payout_destination']
            ]
        ]);
    }

    /**
     * Reconcilie une transaction en allant chercher son statut réel chez le prestataire
     */
    public function reconcile(Transaction $transaction): Transaction
    {
        if ($transaction->status !== 'pending') {
            return $transaction;
        }

        $driver = $this->resolveDriver($transaction->gateway);
        $externalId = $transaction->metadata['external_id'] ?? null;

        if (!$externalId) {
            return $transaction;
        }

        try {
            $verifiedData = $driver->retrieveTransaction($externalId);

            if ($verifiedData->status === 'success') {
                $transaction->update([
                    'status' => 'held',
                    'metadata' => array_merge($transaction->metadata, [
                        'confirmed_at' => now(),
                        'expires_at' => now()->addHours($transaction->escrow_duration ?? 24)
                    ])
                ]);
            } elseif ($verifiedData->status === 'failed') {
                $transaction->update(['status' => 'failed']);
            }

            return $transaction;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Reconciliation failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return $transaction;
        }
    }
}
