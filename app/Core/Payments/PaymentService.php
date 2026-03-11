<?php
namespace App\Core\Payments;

use App\Models\Gateway;
use App\Models\Transaction;
use App\Gateways\Fedapay\FedapayDriver;
use App\Core\Payments\Contract\PaymentsGatewayInterface;
use Exception;

class PaymentService
{
    /**
     * Cette méthode doit retourner le bon Driver selon le type stocké en base
     */
    public function resolveDriver(Gateway $gateway): PaymentsGatewayInterface
    {
        if($gateway->gateway_type == 'fedapay'){
            return new FedapayDriver($gateway->api_key, $gateway->is_live);
        }

        throw new Exception("Le driver que vous fournissez est indisponible");

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
            'metadata' => [
                'external_id' => $paymentInfo->external_id,
                'payment_url' => $paymentInfo->url
            ]
        ]);
    }
}
