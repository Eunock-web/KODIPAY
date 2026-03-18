<?php

namespace App\Core\Payments\Contract;

interface PaymentsGatewayInterface
{
    public function makePayment(int $amount, string $currency, array $options = []): object;

    public function payout(int $amount, string $currency, string $destination): object;

    public function validateWebhook(\Illuminate\Http\Request $request): object;

    public function retrieveTransaction(string $externalId): object;
}
