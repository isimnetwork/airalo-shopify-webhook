<?php
// Load environment variables (adjust if you use a library or different method)
$secret = getenv('SHOPIFY_API_SECRET');

// Get Shopify's HMAC header (Base64 encoded)
$hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

// Get the raw POST payload from Shopify
$payload = file_get_contents('php://input');

// Calculate HMAC with your secret and raw payload
$calculatedHmac = base64_encode(hash_hmac('sha256', $payload, $secret, true));

// Verify the webhook signature in a timing-safe manner
if (!hash_equals($hmacHeader, $calculatedHmac)) {
    http_response_code(401);
    exit('Webhook verification failed');
}

// Decode the JSON payload into an array
$data = json_decode($payload, true);

// TODO: Add your order processing or Airalo API integration here
// Example: error_log(print_r($data, true));

// Respond with HTTP 200 to acknowledge receipt
http_response_code(200);
echo 'Webhook received and verified.';
