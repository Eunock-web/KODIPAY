<?php
namespace App\Gateways\Fedapay;
use App\Core\Payments\Contract\PaymentsGatewayInterface;


class FedapayDriver implements PaymentsGatewayInterface {

    public function __construct(private string $apiKey, private bool $is_live){
        $this->apiKey = $apiKey;
        $this->is_live = $is_live;
    }

    public function makePayment(int $amount, string $currency, array $options): object{
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');
        \FedaPay\Fedapay::setApiKey('YOUR_API_KEY');

        $transaction = \FedaPay\Transaction::create([
            'description' => 'Paiement KODIPAY',
            'amount' => $amount,
            'currency' => ['iso' => $currency],
            'callback_url' => $options['callback_url'],
            'customer' => [
                'firstname' => $options['firstname'],
                'lastname' => $options['lastname'],
                'email' => $options['email'],
            ]
        ]);
        return $transaction;
    }

    public function payout(int $amount, string $currency, string $destination): object{
        return new \stdClass();
    }

    public function validateWebhook(array $payload, array $headers): object{
        return new \stdClass();
    }

}
