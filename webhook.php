<?php
require 'vendor/autoload.php';

use Airalo\AiraloClient;

// Get raw POST data from Shopify webhook
$input = file_get_contents('php://input');
$orderData = json_decode($input, true);

// Set your Airalo credentials (we'll configure env vars in Railway later)
$clientId = getenv('AIRALO_CLIENT_ID');
$clientSecret = getenv('AIRALO_CLIENT_SECRET');

$airalo = new AiraloClient([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
]);

// Authenticate to Airalo
$airalo->authenticate();

// Here you should parse $orderData to get order details (like customer email or order ID)
// This depends on the Shopify webhook payload structure

// Example: assuming you have the order ID or email, fetch orders from Airalo
// This is a simplified example; you’ll want to adjust filters to match your data
$orders = $airalo->orders()->list([
    // Adjust filter parameters as needed
    // 'filter[email]' => $orderData['email'],
]);

if (count($orders) > 0) {
    $order = $orders[0];
    $sims = $order['sims'] ?? [];
    foreach ($sims as $sim) {
        $iccid = $sim['iccid'];
        $simDetails = $airalo->sims()->get($iccid);

        // Log or output SIM info (ICCID, CCID, QR code URL)
        error_log("SIM ICCID: $iccid");
        error_log("SIM CCID: " . ($simDetails['ccid'] ?? 'N/A'));
        error_log("SIM QR Code: " . ($simDetails['qr_code'] ?? 'N/A'));
    }
} else {
    error_log("No matching orders found in Airalo for Shopify order.");
}

// Respond with 200 OK to Shopify webhook
http_response_code(200);
echo json_encode(['status' => 'success']);
