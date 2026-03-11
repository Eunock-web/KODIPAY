<?php

namespace Tests\Unit\Core;

use App\Core\Payments\PaymentService;
use App\Gateways\Fedapay\FedapayDriver;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_fedapay_driver_correctly(): void
    {
        $user = User::factory()->create();
        $gateway = $user->gateways()->create([
            'gateway_type' => 'fedapay',
            'api_key' => 'sk_live_123',
            'is_live' => true,
        ]);

        $service = new PaymentService();
        $driver = $service->resolveDriver($gateway);

        $this->assertInstanceOf(FedapayDriver::class, $driver);
    }

    public function test_it_throws_exception_for_unknown_driver(): void
    {
        $user = User::factory()->create();

        // On force un gateway_type indéfini (ignorant la validation Request pour le test unitaire)
        $gateway = new Gateway([
            'gateway_type' => 'stripe',  // non supporté
            'api_key' => 'test',
            'is_live' => false,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Le driver que vous fournissez est indisponible');

        $service = new PaymentService();
        $service->resolveDriver($gateway);
    }
}
