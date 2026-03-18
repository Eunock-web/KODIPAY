<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Gateway;
use FedaPay\FedaPay;
use FedaPay\Transaction;

$gateway = Gateway::where('gateway_type', 'fedapay')->first();
FedaPay::setEnvironment($gateway->is_live ? 'live' : 'sandbox');
FedaPay::setApiKey($gateway->private_key);

$params = [
    'amount' => 10000,
    'currency' => ['iso' => 'XOF'],
    'description' => 'Test USSD',
    'customer' => [
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'test@example.com',
        'phone_number' => ['number' => '65000000', 'country' => 'bj']
    ]
];

$modes = ['mtn_open', 'moov', 'mtn_ci', 'mtn'];
foreach ($modes as $mode) {
    try {
        echo "Testing mode: $mode\n";
        $transaction = Transaction::create($params);
        $transaction->sendNow($mode, [
            'number' => '65000000',
            'country' => 'bj'
        ]);
        echo "Success for mode: $mode!\n";
    } catch (\Exception $e) {
        echo "Error for $mode: " . $e->getMessage() . "\n";
    }
}
