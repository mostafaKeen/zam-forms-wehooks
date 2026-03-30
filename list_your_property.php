<?php
/**
 * List Your Property Webhook
 * 
 * Receives data via POST, stores it locally in a JSON file, 
 * and forwards it to SPA 1054 in Bitrix24.
 */

// 1. Capture Data
$rawData = file_get_contents('php://input');

// Force reset any server-side PHP caching
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Fallback to $_POST if raw input is empty (for standard form-data)
if (empty($rawData) && !empty($_POST)) {
    $rawData = json_encode($_POST);
}

if (empty($rawData)) {
    http_response_code(400);
    echo "No data received.";
    exit;
}

// Parse data for logging
$data = json_decode($rawData, true);
if (is_null($data)) {
    parse_str($rawData, $data);
}

// 2. Local Logging (Store in list_your_property.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/list_your_property.json', $logEntry, FILE_APPEND);

// 3. Forward to LeadConnector
$targetUrl = 'https://services.leadconnectorhq.com/hooks/rszL6rWgEgcbEK6oPE0K/webhook-trigger/DhiVag7766rqUNftMt0A';

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. Create Item in Bitrix24 SPA 1054
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.item.add.json';

// Mapping incoming data (Based on the structure provided)
$fields = $data['fields'] ?? $data['data']['fields'] ?? [];

$name = $fields['name']['value'] ?? 'List Your Property Lead';
$phone = $fields['email']['value'] ?? '';           // "Phone Number*" label uses id "email"
$email = $fields['field_f4c004c']['value'] ?? '';  // "Email Address *"
$propType = $fields['field_70a4eab']['value'] ?? ''; // "Property Type *"
$community = $fields['field_d91170a']['value'] ?? ''; // "Community / Area *"
$price = $fields['field_03c4f85']['value'] ?? 0;        // "Asking price (AED)*"
$contactMethod = $fields['field_5d714be']['value'] ?? ''; // "Preferred Contact Method"
$description = $fields['field_3d9232c']['value'] ?? '';   // "Property Description"
$fileUrl = $fields['message']['value'] ?? '';        // File attachment link

$bitrixFields = [
    'entityTypeId' => 1054,
    'fields' => [
        'title' => 'List Your Property: ' . $name,
        'assignedById' => 28,               // Sarah
        'sourceId' => 'CALLBACK',            // "Zamprime Website"
        'opportunity' => $price,
        'currencyId' => 'AED',
        'sourceDescription' => "Preferred Contact Method: $contactMethod\nDescription: $description",
        // Custom Fields for SPA 1054
        'ufCrm14_1774873950' => $phone,      // phone
        'ufCrm14_1774873966' => $email,      // email
        'ufCrm14_1774873980' => $propType,   // Property Type
        'ufCrm14_1774873991' => $community,  // Community / Area
        'ufCrm14_1774874002' => $price,      // Asking price (AED)
        'ufCrm14_1774874017' => $contactMethod, // Preferred Contact Method
        'ufCrm14_1774874087' => $description    // Property Description
    ]
];

// Handle File Attachments
if (!empty($fileUrl) && filter_var($fileUrl, FILTER_VALIDATE_URL)) {
    $fileContent = @file_get_contents($fileUrl);
    if ($fileContent) {
        $fileName = basename(parse_url($fileUrl, PHP_URL_PATH));
        if (!strpos($fileName, '.')) $fileName .= '.jpg';
        $bitrixFields['fields']['ufCrm14_1774874123'] = [
            $fileName, base64_encode($fileContent)
        ];
    }
}

$chBitrix = curl_init($bitrixUrl);
curl_setopt($chBitrix, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBitrix, CURLOPT_POST, true);
curl_setopt($chBitrix, CURLOPT_POSTFIELDS, http_build_query($bitrixFields));

$bitrixResponse = curl_exec($chBitrix);
$bitrixCode = curl_getinfo($chBitrix, CURLINFO_HTTP_CODE);
$bitrixData = json_decode($bitrixResponse, true);
curl_close($chBitrix);

// 5. Log Results
$logStatus = date('[Y-m-d H:i:s] ') . "LeadConnector: $httpCode | Bitrix: $bitrixCode | Assigned To: 28";
if ($bitrixCode >= 400) $logStatus .= " | Bitrix Response: " . $bitrixResponse;
file_put_contents(__DIR__ . '/list_your_property_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Response
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'leadconnector' => $httpCode,
    'bitrix' => $bitrixCode,
    'debug_entityTypeId' => 1054,
    'bitrix_response' => $bitrixData
]);
