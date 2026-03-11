<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createTransaction(string $status): Transaction
    {
        $user = User::factory()->create();
        $gateway = $user->gateways()->create([
            'gateway_type' => 'fedapay',
            'api_key' => 'sk_test',
            'is_live' => false,
        ]);

        return Transaction::create([
            'gateway_id' => $gateway->id,
            'amount' => 1000,
            'currency' => 'XOF',
            'status' => $status,
            'metadata' => ['external_id' => 'txn_' . uniqid()],
        ]);
    }

    public function test_scope_held_returns_only_held_transactions(): void
    {
        $held1 = $this->createTransaction('held');
        $held2 = $this->createTransaction('held');
        $pending = $this->createTransaction('pending');
        $released = $this->createTransaction('released');

        $results = Transaction::held()->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $held1->id));
        $this->assertTrue($results->contains('id', $held2->id));
        $this->assertFalse($results->contains('id', $pending->id));
        $this->assertFalse($results->contains('id', $released->id));
    }

    public function test_scope_held_returns_empty_when_no_held_transactions(): void
    {
        $this->createTransaction('pending');
        $this->createTransaction('released');

        $results = Transaction::held()->get();

        $this->assertCount(0, $results);
    }

    public function test_transaction_metadata_is_cast_to_array(): void
    {
        $transaction = $this->createTransaction('pending');

        $this->assertIsArray($transaction->fresh()->metadata);
        $this->assertArrayHasKey('external_id', $transaction->fresh()->metadata);
    }

    public function test_transaction_belongs_to_gateway(): void
    {
        $transaction = $this->createTransaction('pending');

        $this->assertInstanceOf(Gateway::class, $transaction->gateway);
    }
}
