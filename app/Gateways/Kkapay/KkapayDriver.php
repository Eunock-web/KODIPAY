<?php
namespace App\Gateways\Kkapay;

use App\Core\Payments\Contract\PaymentsGatewayInterface;
use Illuminate\Support\Facades\Http;

class KkapayDriver implements PaymentsGatewayInterface
{
    public function __construct(
        private string $apiKey,
        private bool $is_live,
        private ?string $publicKey = null
    ) {}

    public function makePayment(int $amount, string $currency, array $options = []): object
    {
        $url = $this->is_live
            ? 'https://api.kkapay.com/v1/create'
            : 'https://sandbox-api.kkapay.com/v1/create';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Public-Key' => $this->publicKey
        ])->post($url, [
            'amount' => $amount,
            'currency' => $currency,
            'transaction_id' => $options['transaction_id'] ?? uniqid('KODIPAY_'),
            'callback_url' => route('webhooks.kkapay', ['gateway_id' => $options['gateway_id'] ?? 'unknown']),
            'description' => $options['description'] ?? 'Paiement KODIPAY',
            'customer' => [
                'email' => $options['email'] ?? null,
                'name' => ($options['firstname'] ?? '') . ' ' . ($options['lastname'] ?? ''),
            ]
        ]);

        if ($response->failed()) {
            throw new \Exception('Erreur KKAPay : ' . ($response->json()['message'] ?? $response->body()));
        }

        $data = $response->json();

        if (!isset($data['id']) || !isset($data['payment_url'])) {
            throw new \Exception('Réponse KKAPay invalide : ' . json_encode($data));
        }

        return (object) [
            'status' => 'success',
            'external_id' => $data['id'],
            'url' => $data['payment_url'],
            'token' => $data['token'] ?? null
        ];
    }

    public function validateWebhook(array $payload, array $headers): object
    {
        // KKApay signature verification
        $signature = $headers['x-kkapay-signature'][0] ?? $headers['X-Kkapay-Signature'][0] ?? '';

        if (empty($signature)) {
            throw new \Exception('Signature KKAPay manquante.');
        }

        // According to common practices and similar gateways, we verify using the API key
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $this->apiKey);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Signature KKAPay invalide.');
        }

        return (object) [
            'status' => 'success',
            'event' => (isset($payload['status']) && $payload['status'] === 'SUCCESS') ? 'transaction.approved' : 'transaction.failed',
            'external_id' => $payload['transaction_id'] ?? ($payload['id'] ?? null)
        ];
    }

    public function payout(int $amount, string $currency, string $destination): object
    {
        $url = $this->is_live
            ? 'https://api.kkapay.com/v1/payout'
            : 'https://sandbox-api.kkapay.com/v1/payout';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Public-Key' => $this->publicKey
        ])->post($url, [
            'amount' => $amount,
            'currency' => $currency,
            'destination' => $destination,
            'reference' => uniqid('PAYOUT_'),
        ]);

        if ($response->failed()) {
            throw new \Exception('Erreur Payout KKAPay : ' . ($response->json()['message'] ?? $response->body()));
        }

        $data = $response->json();

        return (object) [
            'status' => 'success',
            'external_id' => $data['id'] ?? null,
            'raw' => $data
        ];
    }

    public function retrieveTransaction(string $externalId): object
    {
        $url = $this->is_live
            ? "https://api.kkapay.com/v1/transactions/{$externalId}"
            : "https://sandbox-api.kkapay.com/v1/transactions/{$externalId}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Public-Key' => $this->publicKey
        ])->get($url);

        if ($response->failed()) {
            throw new \Exception('Erreur KKAPay (retrieveTransaction) : ' . ($response->json()['message'] ?? $response->body()));
        }

        $data = $response->json();

        // KKApay statuses → KODIPAY statuses
        $status = 'pending';
        if (($data['status'] ?? '') === 'SUCCESS') {
            $status = 'success';
        } elseif (in_array($data['status'] ?? '', ['FAILED', 'CANCELLED', 'REJECTED'])) {
            $status = 'failed';
        }

        return (object) [
            'status' => $status,
            'external_id' => $data['id'] ?? $externalId,
            'event' => 'transaction.' . strtolower($data['status'] ?? 'pending'),
            'raw_data' => $data
        ];
    }
}
