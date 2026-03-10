<?php
namespace App\Gateways\Fedapay;
use App\Core\Payments\Contract\PaymentsGatewayInterface;


class FedapayDriver implements PaymentsGatewayInterface {

    public function __construct(private string $apiKey, private bool $is_live){
        $this->apiKey = $apiKey;
        $this->is_live = $is_live;
    }

    public function makePayment(int $amount, string $currency, array $options): object{
        return new \stdClass();
    }

    public function payout(int $amount, string $currency, string $destination): object{
        return new \stdClass();
    }

    public function validateWebhook(array $payload, array $headers): object{
        return new \stdClass();
    }

}
