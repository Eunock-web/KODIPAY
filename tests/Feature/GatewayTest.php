<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_gateway(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/gateways', [
                'gateway_type' => 'fedapay',
                'public_key' => 'sk_sandbox_test_123',
                'is_live' => false,
            ]);

        $response
            ->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'message', 'gateway_id']);

        $this->assertDatabaseHas('gateways', [
            'user_id' => $user->id,
            'gateway_type' => 'fedapay',
        ]);
    }

    public function test_gateway_creation_fails_with_invalid_type(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/gateways', [
                'gateway_type' => 'invalid_gateway',
                'public_key' => 'sk_sandbox_test_123',
                'is_live' => false,
            ]);

        $response->assertStatus(422);
    }

    public function test_gateway_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/gateways', [
            'gateway_type' => 'fedapay',
            'api_key' => 'sk_sandbox_test_123',
        ]);

        $response->assertStatus(401);
    }

    public function test_gateway_creation_fails_without_api_key(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/gateways', [
                'gateway_type' => 'fedapay',
            ]);

        $response->assertStatus(422);
    }
}
