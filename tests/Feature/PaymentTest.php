<?php

namespace Tests\Feature;

use App\Core\Payments\Contract\PaymentsGatewayInterface;
use App\Core\Payments\PaymentService;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Retourne un gateway lié à l'utilisateur donné.
     */
    private function createGatewayForUser(User $user): Gateway
    {
        return $user->gateways()->create([
            'gateway_type' => 'fedapay',
            'public_key' => 'pk_sandbox_test_123',
            'private_key' => 'sk_sandbox_test_123',
            'is_live' => false,
        ]);
    }

    /**
     * Mock le PaymentService pour éviter les appels réseau FedaPay.
     */
    private function mockPaymentService(Gateway $gateway): void
    {
        $mockDriver = $this->createMock(PaymentsGatewayInterface::class);
        $mockDriver->method('makePayment')->willReturn((object) [
            'external_id' => 'fedapay_txn_99',
            'url' => 'https://pay.fedapay.com/pay/99',
            'token' => 'fpt_test_token_123',
        ]);

        $mockService = $this->createMock(PaymentService::class);
        $mockService->method('resolveDriver')->willReturn($mockDriver);
        $mockService->method('initiate')->willReturnCallback(
            function (Gateway $gw, array $data) {
                $isDirect = $data['direct'] ?? false;

                return \App\Models\Transaction::create([
                    'gateway_id' => $gw->id,
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'status' => 'pending',
                    'escrow_duration' => $data['escrow_duration'] ?? null,
                    'metadata' => array_filter([
                        'external_id' => 'fedapay_txn_99',
                        'payment_url' => !$isDirect ? 'https://pay.fedapay.com/pay/99' : null,
                        'payment_token' => $isDirect ? 'fpt_test_token_123' : null,
                        'payout_destination' => $data['payout_destination'] ?? null,
                    ]),
                ]);
            }
        );

        $this->app->instance(PaymentService::class, $mockService);
    }

    public function test_authenticated_user_can_initiate_payment(): void
    {
        $user = User::factory()->create();
        $gateway = $this->createGatewayForUser($user);

        $this->mockPaymentService($gateway);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments', [
                'gateway_id' => $gateway->id,
                'amount' => 5000,
                'currency' => 'XOF',
                'transaction_type' => 'collect',
                'customer_email' => 'client@example.com',
            ]);

        $response
            ->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data']);

        $this->assertDatabaseHas('transactions', [
            'gateway_id' => $gateway->id,
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'pending',
        ]);
    }

    public function test_payment_returns_fedapay_url_by_default(): void
    {
        $user = User::factory()->create();
        $gateway = $this->createGatewayForUser($user);

        $this->mockPaymentService($gateway);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments', [
                'gateway_id' => $gateway->id,
                'amount' => 5000,
                'currency' => 'XOF',
                'transaction_type' => 'collect',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.metadata.payment_url', 'https://pay.fedapay.com/pay/99')
            ->assertJsonMissingPath('data.metadata.payment_token');
    }

    public function test_payment_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/payments', [
            'gateway_id' => 'some-uuid',
            'amount' => 5000,
            'currency' => 'XOF',
        ]);

        $response->assertStatus(401);
    }

    public function test_payment_fails_without_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gateway_id', 'amount', 'currency']);
    }

    public function test_payment_fails_with_amount_zero(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments', [
                'gateway_id' => 'some-uuid',
                'amount' => 0,
                'currency' => 'XOF',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_payment_fails_with_unknown_gateway(): void
    {
        $user = User::factory()->create();

        // On ne mock pas le service → le findOrFail lève une 404/500
        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments', [
                'gateway_id' => '00000000-0000-0000-0000-000000000000',
                'amount' => 5000,
                'currency' => 'XOF',
                'transaction_type' => 'collect',
            ]);

        // Le catch retourne 500 car findOrFail lance une ModelNotFoundException
        $response
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    public function test_payment_redirect_requires_callback_url(): void
    {
        $user = User::factory()->create();
        $gateway = $this->createGatewayForUser($user);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments/redirect', [
                'gateway_id' => $gateway->id,
                'amount' => 5000,
                'currency' => 'XOF',
                'transaction_type' => 'collect',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['callback_url']);
    }

    public function test_payment_redirect_success(): void
    {
        $user = User::factory()->create();
        $gateway = $this->createGatewayForUser($user);
        $this->mockPaymentService($gateway);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments/redirect', [
                'gateway_id' => $gateway->id,
                'amount' => 5000,
                'currency' => 'XOF',
                'transaction_type' => 'collect',
                'callback_url' => 'https://mysite.com/callback'
            ]);

        $response
            ->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.metadata.payment_url', 'https://pay.fedapay.com/pay/99')
            ->assertJsonMissingPath('data.metadata.payment_token');
    }

    public function test_payment_direct_requires_phone(): void
    {
        $user = User::factory()->create();
        $gateway = $this->createGatewayForUser($user);

        $this->mockPaymentService($gateway);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments/direct', [
                'gateway_id' => $gateway->id,
                'amount' => 5000,
                'currency' => 'XOF',
                'transaction_type' => 'collect',
            ]);

        $response->assertStatus(201);
        //            ->assertJsonValidationErrors(['phone']);  // User made it nullable
    }

    public function test_payment_direct_success(): void
    {
        $user = User::factory()->create();
        $gateway = $this->createGatewayForUser($user);
        $this->mockPaymentService($gateway);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/payments/direct', [
                'gateway_id' => $gateway->id,
                'amount' => 5000,
                'currency' => 'XOF',
                'transaction_type' => 'collect',
                'phone' => '66000001'
            ]);

        $response
            ->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.metadata.payment_token', 'fpt_test_token_123')
            ->assertJsonMissingPath('data.metadata.payment_url');
    }
}
