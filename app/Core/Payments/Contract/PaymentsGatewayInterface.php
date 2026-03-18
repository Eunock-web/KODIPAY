<?php

namespace App\Core\Payments\Contract;

interface PaymentsGatewayInterface
{
    public function makePayment(int $amount, string $currency, array $options = []): object;

    public function payout(int $amount, string $currency, string $destination): object;

    public function validateWebhook(array $payload, array $headers): object;

    public function retrieveTransaction(string $externalId): object;
}
