<?php
namespace App\Gateways\Fedapay;
use App\Core\Payments\Contract\PaymentsGatewayInterface;
use FedaPay\Transaction;

class FedapayDriver implements PaymentsGatewayInterface {

    public function __construct(private string $apiKey, private bool $is_live){}

    public function makePayment(int $amount, string $currency, array $options): object{
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');
        \FedaPay\FedaPay::setApiKey($this->apiKey);

        // Création de la transaction chez FedaPay
        $fedapayTransaction = Transaction::create([
            'amount' => $amount,
            'currency' => ['iso' => $currency],
            'description' => $options['description'] ?? 'Paiement KODIPAY',
            'callback_url' => $options['callback_url'] ?? null,
            'customer' => [
                'firstname' => $options['firstname'] ?? 'Client',
                'lastname' => $options['lastname'] ?? 'KODIPAY',
                'email' => $options['email']
            ]
        ]);

        $token = $fedapayTransaction->generateToken();

        return (object) [
            'external_id' => $fedapayTransaction->id,
            'url' => $token->url
        ];
    }

    public function payout(int $amount, string $currency, string $destination): object{
        return new \stdClass();
    }

    public function validateWebhook(array $payload, array $headers): object{
        return new \stdClass();
    }

}
