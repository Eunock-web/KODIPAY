<?php

namespace Tests\Feature;

use App\Gateways\Kkapay\KkapayDriver;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KkapayDriverTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'kk_test_123';
    private string $publicKey = 'pk_test_456';

    public function test_make_payment_success(): void
    {
        Http::fake([
            'https://sandbox-api.kkapay.com/v1/create' => Http::response([
                'id' => 'kk_txn_789',
                'payment_url' => 'https://pay.kkapay.com/pay/789',
                'token' => 'kk_token_abc'
            ], 200)
        ]);

        $driver = new KkapayDriver($this->apiKey, false, $this->publicKey);
        $response = $driver->makePayment(1000, 'XOF', [
            'email' => 'test@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'gateway_id' => 'gw_123'
        ]);

        $this->assertEquals('kk_txn_789', $response->external_id);
        $this->assertEquals('https://pay.kkapay.com/pay/789', $response->url);
        $this->assertEquals('kk_token_abc', $response->token);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer ' . $this->apiKey) &&
                $request->hasHeader('X-Public-Key', $this->publicKey) &&
                $request['amount'] == 1000 &&
                $request['currency'] == 'XOF' &&
                $request['customer']['email'] == 'test@example.com';
        });
    }

    public function test_make_payment_failure(): void
    {
        Http::fake([
            'https://sandbox-api.kkapay.com/v1/create' => Http::response([
                'message' => 'Invalid API Key'
            ], 401)
        ]);

        $driver = new KkapayDriver($this->apiKey, false, $this->publicKey);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Erreur KKAPay : Invalid API Key');

        $driver->makePayment(1000, 'XOF', []);
    }

    public function test_validate_webhook_success(): void
    {
        $payload = [
            'status' => 'SUCCESS',
            'transaction_id' => 'kk_txn_789',
            'amount' => 1000
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $this->apiKey);
        $headers = ['x-kkapay-signature' => [$signature]];

        $driver = new KkapayDriver($this->apiKey, false);
        $result = $driver->validateWebhook($payload, $headers);

        $this->assertEquals('success', $result->status);
        $this->assertEquals('transaction.approved', $result->event);
        $this->assertEquals('kk_txn_789', $result->external_id);
    }

    public function test_validate_webhook_invalid_signature(): void
    {
        $payload = ['status' => 'SUCCESS'];
        $headers = ['x-kkapay-signature' => ['wrong_signature']];

        $driver = new KkapayDriver($this->apiKey, false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Signature KKAPay invalide.');

        $driver->validateWebhook($payload, $headers);
    }

    public function test_payout_success(): void
    {
        Http::fake([
            'https://sandbox-api.kkapay.com/v1/payout' => Http::response([
                'id' => 'kk_payout_123',
                'status' => 'PENDING'
            ], 200)
        ]);

        $driver = new KkapayDriver($this->apiKey, false, $this->publicKey);
        $response = $driver->payout(5000, 'XOF', '22966001122');

        $this->assertEquals('success', $response->status);
        $this->assertEquals('kk_payout_123', $response->external_id);

        Http::assertSent(function ($request) {
            return $request['amount'] == 5000 &&
                $request['destination'] == '22966001122';
        });
    }
}
