<?php
/**
 * Careers Lead Webhook Implementation
 * 
 * Captures data, stores it in careers_lead.json,
 * and forwards to LeadConnector.
 */

// 1. Capture Data
$rawData = file_get_contents('php://input');

// Reset any PHP caching
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

// 2. Local Logging (Store in careers_lead.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/careers_lead.json', $logEntry, FILE_APPEND);

// 3. Forward to LeadConnector
$targetUrl = 'https://services.leadconnectorhq.com/hooks/rszL6rWgEgcbEK6oPE0K/webhook-trigger/pttY0n4YDeY8iLRVsQ2E';

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$lcResponse = curl_exec($ch);
$lcHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. Create Lead in Bitrix24
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.item.add.json';

// Mapping incoming data
$formFields = $data['data']['fields'] ?? $data['fields'] ?? [];
$formMeta = $data['data']['meta'] ?? $data['meta'] ?? [];

$name = $formFields['name']['value'] ?? 'Careers Lead';
$email = $formFields['email']['value'] ?? '';
$phone = $formFields['field_7bd6ec1']['value'] ?? '';
$message = $formFields['message']['value'] ?? '';

// Collect unmapped fields into meta data string
$metaLines = [];
$mappedKeys = ['name', 'email', 'field_7bd6ec1', 'field_ffe901d']; // message goes into metadata

foreach ($formFields as $id => $field) {
    if (!in_array($id, $mappedKeys) && !empty($field['value'])) {
        $label = $field['title'] ?: $id;
        $metaLines[] = "$label: {$field['value']}";
    }
}

// Add system meta
foreach ($formMeta as $key => $meta) {
    if (!empty($meta['value'])) {
        $label = $meta['title'] ?: $key;
        $metaLines[] = "$label: {$meta['value']}";
    }
}

$metaDataString = implode("\n", $metaLines);

$bitrixFields = [
    'entityTypeId' => 1038,
    'fields' => [
        'TITLE' => 'Careers Lead: ' . $name,
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'ASSIGNED_BY_ID' => 28,               // Sarah
    ],
    'params' => ['REGISTER_SONET_EVENT' => 'Y']
];

if (!empty($phone)) {
    $bitrixFields['fields']['UF_CRM_8_1772192069412'] = $phone;
}
if (!empty($email)) {
    $bitrixFields['fields']['UF_CRM_8_1772192889128'] = $email;
}


// Handle File Attachments
if (!empty($formFields['field_ffe901d']['value']) && filter_var($formFields['field_ffe901d']['value'], FILTER_VALIDATE_URL)) {
    $fileUrl = $formFields['field_ffe901d']['value'];
    $fileContent = @file_get_contents($fileUrl);
    if ($fileContent) {
        $fileName = basename(parse_url($fileUrl, PHP_URL_PATH));
        if (!strpos($fileName, '.')) $fileName .= '.jpg';
        $bitrixFields['fields']['UF_CRM_8_1772193399534'] = [
            $fileName, base64_encode($fileContent)
        ];
    }
}

$chBitrix = curl_init($bitrixUrl);
curl_setopt($chBitrix, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBitrix, CURLOPT_POST, true);
curl_setopt($chBitrix, CURLOPT_POSTFIELDS, http_build_query($bitrixFields));

$bitrixResponse = curl_exec($chBitrix);
$bitrixHttpCode = curl_getinfo($chBitrix, CURLINFO_HTTP_CODE);
curl_close($chBitrix);

// 5. Log Results
$logStatus = date('[Y-m-d H:i:s] ') . "LeadConnector: $lcHttpCode | Bitrix: $bitrixResponse | Assigned To: 28";
file_put_contents(__DIR__ . '/careers_lead_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Response
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'leadconnector' => $lcHttpCode,
    'bitrix' => $bitrixHttpCode,
    'bitrix_response' => json_decode($bitrixResponse, true)
]);
