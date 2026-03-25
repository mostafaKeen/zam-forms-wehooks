<?php
/**
 * UAE Investor Lead Webhook
 * 
 * Receives data via POST and stores it locally in a JSON file.
 */

// 1. Capture Data
$rawData = file_get_contents('php://input');

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

// 2. Local Logging (Store in uae_investor.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/uae_investor.json', $logEntry, FILE_APPEND);

// 3. Send to Bitrix24
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.lead.add.json';

// Mapping incoming data
$fields = $data['fields'] ?? [];
$name = $fields['name']['value'] ?? 'UAE Investor Lead';
$email = $fields['email']['value'] ?? '';
$phone = $fields['phone']['value'] ?? '';
$notes = $fields['additional_notes']['value'] ?? '';

// Collect unmapped fields into meta data string
$metaDataString = "";
$mappedFieldIds = ['name', 'email', 'phone', 'additional_notes'];

foreach ($fields as $id => $field) {
    if (!in_array($id, $mappedFieldIds)) {
        $title = $field['title'] ?? $id;
        $val = $field['value'] ?? '';
        $metaDataString .= "$title: $val\n";
    }
}

// Add meta information
if (isset($data['meta']) && is_array($data['meta'])) {
    foreach ($data['meta'] as $key => $meta) {
        $title = $meta['title'] ?? $key;
        $val = $meta['value'] ?? '';
        $metaDataString .= "$title: $val\n";
    }
}

$bitrixFields = [
    'fields' => [
        'TITLE' => 'UAE Investor: ' . $name,
        'NAME' => $name,
        'COMMENTS' => $notes,
        'ASSIGNED_BY_ID' => 1,            // Default ID (RE/MAX ZAM)
        'SOURCE_ID' => 'CALLBACK',         // "Zamprime Website"
        'UF_CRM_1773862147183' => '21060', // Subsource: "UAE Investment Lead"
        'UF_CRM_1774359455' => trim($metaDataString), // custom "meta data" field
    ],
    'params' => ['REGISTER_SONET_EVENT' => 'Y']
];

if (!empty($phone)) {
    $bitrixFields['fields']['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
}
if (!empty($email)) {
    $bitrixFields['fields']['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
}

$chBitrix = curl_init($bitrixUrl);
curl_setopt($chBitrix, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBitrix, CURLOPT_POST, true);
curl_setopt($chBitrix, CURLOPT_POSTFIELDS, http_build_query($bitrixFields));

$bitrixResponse = curl_exec($chBitrix);
$bitrixCode = curl_getinfo($chBitrix, CURLINFO_HTTP_CODE);
curl_close($chBitrix);

// 4. Forward to LeadConnector
$targetUrl = 'https://services.leadconnectorhq.com/hooks/rszL6rWgEgcbEK6oPE0K/webhook-trigger/ie8zM9uhsIl2O54gMORo';

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

// Log the results
$forwardLog = date('[Y-m-d H:i:s] ') . "Bitrix: $bitrixCode | LeadConnector: $httpCode | Assigned To: 1" . PHP_EOL;
file_put_contents(__DIR__ . '/uae_investor_forward_log.txt', $forwardLog, FILE_APPEND);

// Respond to the sender
if ($bitrixCode < 400 && $httpCode < 400) {
    echo "Data processed and forwarded successfully.";
} else {
    http_response_code(500);
    echo "Processing completed with status - Bitrix: $bitrixCode, LeadConnector: $httpCode";
}
