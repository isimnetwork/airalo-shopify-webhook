<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

// Get secrets from environment variables
$sharedSecret = getenv('SHOPIFY_API_SECRET');
$airaloApiKey = getenv('AIRALO_CLIENT_ID');
$airaloApiSecret = getenv('AIRALO_CLIENT_SECRET');

// Read incoming webhook payload and Shopify HMAC header
$requestBody = file_get_contents('php://input');
$hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

// Verify webhook authenticity
$calculatedHmac = base64_encode(hash_hmac('sha256', $requestBody, $sharedSecret, true));
if (!hash_equals($hmacHeader, $calculatedHmac)) {
    http_response_code(401);
    echo 'Invalid HMAC';
    exit;
}

// Decode Shopify order data
$orderData = json_decode($requestBody, true);

$client = new Client();

foreach ($orderData['line_items'] as $item) {
    $sku = $item['sku'];
    $quantity = $item['quantity'];
    $email = $orderData['email'] ?? '';

    try {
        $response = $client->request('POST', 'https://partners-api.airalo.com/api/v2/orders', [
            'headers' => [
                'X-API-Key' => $airaloApiKey,
                'X-API-Secret' => $airaloApiSecret,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'external_order_id' => $orderData['id'],
                'package_id' => $sku,
                'quantity' => $quantity,
                'email' => $email,
            ],
        ]);
        file_put_contents('airalo_success_log.txt', $response->getBody() . "\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('airalo_error_log.txt', $e->getMessage() . "\n", FILE_APPEND);
    }
}

http_response_code(200);
echo 'Webhook received';
