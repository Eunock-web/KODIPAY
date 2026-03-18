<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Gateway;
use FedaPay\FedaPay;
use FedaPay\Transaction;

$gateway = Gateway::where('gateway_type', 'fedapay')->first();
if (!$gateway) {
    die("No fedapay gateway found\n");
}

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
        'phone_number' => [
            'number' => '65000000',
            'country' => 'bj'
        ]
    ]
];

try {
    $transaction = Transaction::create($params);
    $token = $transaction->generateToken();
    
    // Test raw curl to see FedaPay error response
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://sandbox-api.fedapay.com/v1/mtn");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'token' => $token->token,
        'number' => '65000000',
        'country' => 'bj'
    ]));
    
    $headers = array();
    $headers[] = "Authorization: Bearer {$gateway->private_key}";
    $headers[] = "Content-Type: application/json";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $http_status\n";
    echo "Response: $result\n";
    
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
