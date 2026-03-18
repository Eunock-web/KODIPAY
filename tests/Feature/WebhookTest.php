<?php

namespace Tests\Feature;

use App\Core\Payments\Contract\PaymentsGatewayInterface;
use App\Core\Payments\PaymentService;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function createTransactionAndGateway(): array
    {
        $user = User::factory()->create();
        $gateway = $user->gateways()->create([
            'gateway_type' => 'fedapay',
            'public_key' => 'pk_test_123',
            'private_key' => 'sk_test_123',
            'is_live' => false,
        ]);

        $transaction = Transaction::create([
            'gateway_id' => $gateway->id,
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'pending',
            'escrow_duration' => 24,
            'metadata' => [
                'external_id' => 'fedapay_txn_777',
            ],
        ]);

        return [$gateway, $transaction];
    }

    public function test_webhook_successfully_processes_approved_transaction(): void
    {
        [$gateway, $transaction] = $this->createTransactionAndGateway();

        // On mock le driver pour valider le webhook sans dépendre de la signature réelle FedaPay
        $mockDriver = $this->createMock(PaymentsGatewayInterface::class);
        $mockDriver->method('validateWebhook')->willReturn((object) [
            'status' => 'success',
            'event' => 'transaction.approved',
            'external_id' => 'fedapay_txn_777',
            'raw_data' => [],
        ]);

        $mockService = $this->createMock(PaymentService::class);
        $mockService->method('resolveDriver')->willReturn($mockDriver);

        $this->app->instance(PaymentService::class, $mockService);

        $response = $this->postJson("/api/webhooks/fedapay/{$gateway->id}", [
            'event' => 'transaction.approved',
            // les autres données ne sont pas examinées par test grace au mock
        ], [
            'HTTP_X_FEDAPAY_SIGNATURE' => 'dummy_signature'
        ]);

        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'Event processed']);

        $transaction->refresh();
        $this->assertEquals('held', $transaction->status);
        $this->assertArrayHasKey('confirmed_at', $transaction->metadata);
        $this->assertArrayHasKey('expires_at', $transaction->metadata);
    }

    public function test_webhook_fails_if_signature_is_invalid(): void
    {
        [$gateway, $transaction] = $this->createTransactionAndGateway();

        // Si la signature ou payload est invalide, le driver lance une exception
        $mockDriver = $this->createMock(PaymentsGatewayInterface::class);
        $mockDriver->method('validateWebhook')->willThrowException(new \Exception('Validation Webhook échouée'));

        $mockService = $this->createMock(PaymentService::class);
        $mockService->method('resolveDriver')->willReturn($mockDriver);

        $this->app->instance(PaymentService::class, $mockService);

        $response = $this->postJson("/api/webhooks/fedapay/{$gateway->id}", [], [
            'HTTP_X_FEDAPAY_SIGNATURE' => 'invalid_signature'
        ]);

        $response
            ->assertStatus(400)
            ->assertJson(['error' => 'Validation Webhook échouée']);

        $this->assertEquals('pending', $transaction->fresh()->status);
    }

    public function test_webhook_fails_on_unknown_transaction(): void
    {
        [$gateway, $transaction] = $this->createTransactionAndGateway();

        $mockDriver = $this->createMock(PaymentsGatewayInterface::class);
        $mockDriver->method('validateWebhook')->willReturn((object) [
            'status' => 'success',
            'event' => 'transaction.approved',
            'external_id' => 'unknown_id',  // Txn introuvable !
        ]);

        $mockService = $this->createMock(PaymentService::class);
        $mockService->method('resolveDriver')->willReturn($mockDriver);
        $this->app->instance(PaymentService::class, $mockService);

        $response = $this->postJson("/api/webhooks/fedapay/{$gateway->id}", []);

        // 400 car l'erreur levée par "firstOrFail()" devient ModelNotFoundException
        // qui est convertie en 400 dans le catch global du PaymentController::callback
        $response->assertStatus(400);
    }
}
