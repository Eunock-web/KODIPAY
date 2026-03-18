<?php
require __DIR__ . '/vendor/autoload.php';

use FedaPay\FedaPay;

FedaPay::setEnvironment('sandbox');
FedaPay::setApiKey('sk_sandbox_test');

echo FedaPay::getBaseUrl();

