<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$gateway = \App\Models\Gateway::where('gateway_type', 'fedapay')->first();
$key = $gateway->private_key;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://sandbox-api.fedapay.com/v1/mtn");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'token' => 'test'
]));

$headers = array();
$headers[] = "Authorization: Bearer {$key}";
$headers[] = "Content-Type: application/json";
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
echo "Sandbox response: $result\n";
curl_close($ch);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.fedapay.com/v1/mtn");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'token' => 'test'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = curl_exec($ch);
echo "Live response: $result\n";
curl_close($ch);

