<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use FedaPay\FedaPay;
use FedaPay\Transaction;

FedaPay::setEnvironment('sandbox');
// Obtenir la clé sandbox de config ou .env
$privateKey = config('services.fedapay.private_key'); // Ou on peut dire $privateKey = env('FEDAPAY_SECRET_KEY');
if (empty($privateKey)) {
    $privateKey = env('FEDAPAY_SECRET_KEY');
}
FedaPay::setApiKey($privateKey);

$params = [
    'amount' => 10000,
    'currency' => ['iso' => 'XOF'],
    'description' => 'Test USSD',
    'customer' => [
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'test@example.com',
        'phone_number' => [
            'number' => '65000000',
            'country' => 'bj'
        ]
    ]
];

try {
    echo "Creating transaction...\n";
    $transaction = Transaction::create($params);
    echo "Transaction ID: " . $transaction->id . "\n";
    
    echo "Generating token...\n";
    $token = $transaction->generateToken();
    echo "Token: " . $token->token . "\n";
    
    echo "Sending USSD push (sendNow)...\n";
    $transaction->sendNow('mtn', [
        'number' => '65000000',
        'country' => 'bj'
    ]);
    echo "USSD push triggered via sendNow!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e instanceof \FedaPay\Error\ApiConnection) {
        echo "API Connection Error\n";
    }
}
