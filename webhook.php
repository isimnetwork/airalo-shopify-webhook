<?php
// Load env variables (adjust your method)
$shopifySecret = getenv('SHOPIFY_API_SECRET');
$shopifyToken = getenv('SHOPIFY_ADMIN_API_ACCESS_TOKEN');
$shopifyStoreUrl = getenv('SHOPIFY_STORE_URL'); // e.g. "isim.network"

// Airalo API config - fill in your details here
$airaloApiBase = "https://partners.airalo.com/api/v1"; // confirm actual base URL
$airaloApiToken = getenv('AIRALO_API_TOKEN'); // you need to add this env var

// 1. Verify Shopify webhook HMAC signature
$hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
$payload = file_get_contents('php://input');
$calculatedHmac = base64_encode(hash_hmac('sha256', $payload, $shopifySecret, true));
if (!hash_equals($hmacHeader, $calculatedHmac)) {
    http_response_code(401);
    exit('Webhook verification failed');
}

// 2. Decode webhook payload JSON
$data = json_decode($payload, true);
$orderId = $data['id'] ?? null;
$lineItems = $data['line_items'] ?? [];

if (!$orderId || empty($lineItems)) {
    http_response_code(400);
    exit('Invalid order data');
}

// 3. Fetch CCIDs from Airalo API for products purchased
function fetchCcidsFromAiralo(array $products, string $apiBase, string $apiToken): array {
    $ccids = [];

    foreach ($products as $product) {
        $sku = $product['sku'] ?? null;
        if (!$sku) continue;

        // Example Airalo endpoint - adjust according to Airalo docs
        $url = "$apiBase/products?sku=$sku";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiToken",
            "Accept: application/json",
        ]);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 200) {
            $result = json_decode($response, true);
            if (!empty($result['data'][0]['ccid'])) {
                $ccids[] = $result['data'][0]['ccid'];
            }
        }
    }

    return $ccids;
}

$ccids = fetchCcidsFromAiralo($lineItems, $airaloApiBase, $airaloApiToken);
if (empty($ccids)) {
    // No CCIDs found - you can log this or handle differently
    $notes = "No CCIDs found for purchased products.";
} else {
    $notes = "Airalo eSIM CCIDs: " . implode(", ", $ccids);
}

// 4. Update Shopify order notes
function updateShopifyOrderNotes($orderId, $notes, $token, $storeUrl) {
    $url = "https://$storeUrl/admin/api/2023-04/orders/$orderId.json";

    $data = [
        'order' => [
            'id' => $orderId,
            'note' => $notes
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Shopify-Access-Token: $token"
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode >= 200 && $httpcode < 300) {
        return json_decode($response, true);
    } else {
        error_log("Failed to update order notes: $response");
        return false;
    }
}

updateShopifyOrderNotes($orderId, $notes, $shopifyToken, $shopifyStoreUrl);

// 5. Respond to Shopify with HTTP 200 OK
http_response_code(200);
echo 'Webhook processed successfully.';
