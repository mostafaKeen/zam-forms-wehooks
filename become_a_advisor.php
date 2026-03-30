<?php
/**
 * Become a Advisor Webhook Implementation
 * 
 * Captures data, stores it in become_a_advisor.json,
 * and forwards to LeadConnector.
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

// 2. Local Logging (Store in become_a_advisor.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/become_a_advisor.json', $logEntry, FILE_APPEND);

// 3. Forward to LeadConnector
$targetUrl = 'https://services.leadconnectorhq.com/hooks/rszL6rWgEgcbEK6oPE0K/webhook-trigger/u3oNMk5jHYu0eCXB1bOK';

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

$name = $formFields['field_d2664cd']['value'] ?? 'Advisor Lead';
$email = $formFields['field_1cddc76']['value'] ?? '';
$phone = $formFields['field_ceedb32']['value'] ?? '';

// Collect unmapped fields into meta data string
$metaLines = [];
$mappedKeys = ['field_d2664cd', 'field_1cddc76', 'field_ceedb32', 'field_20e883f', 'field_cb7c0d0'];

foreach ($formFields as $id => $field) {
    if (!in_array($id, $mappedKeys) && !empty($field['value']) && ($field['type'] ?? '') !== 'step' && ($field['type'] ?? '') !== 'html') {
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
        'TITLE' => 'Become an Advisor: ' . $name,
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'ASSIGNED_BY_ID' => 28,               // Sarah
    ],
    'params' => ['REGISTER_SONET_EVENT' => 'Y']
];

if (!empty($phone)) {
    $bitrixFields['fields']['ufCrm8_1772192069412'] = $phone;
    $bitrixFields['fields']['ufCrm8_177219206942'] = $phone; // Fallback for either typo
}
if (!empty($email)) {
    $bitrixFields['fields']['ufCrm8_1772192889128'] = $email;
}
if (!empty($metaDataString)) {
    $bitrixFields['fields']['ufCrm8_1774869739'] = $metaDataString;
}

// Handle File Attachments
$fileFieldsMapping = [
    'field_20e883f' => 'ufCrm8_1772193399534', // map to CV
    'field_cb7c0d0' => 'ufCrm8_1772193207046'  // map to Photo
];

foreach ($fileFieldsMapping as $fId => $crmField) {
    if (!empty($formFields[$fId]['value']) && filter_var($formFields[$fId]['value'], FILTER_VALIDATE_URL)) {
        $fileUrl = $formFields[$fId]['value'];
        $fileContent = @file_get_contents($fileUrl);
        if ($fileContent) {
            $fileName = basename(parse_url($fileUrl, PHP_URL_PATH));
            if (!strpos($fileName, '.')) $fileName .= '.jpg';
            $bitrixFields['fields'][$crmField] = [
                $fileName, base64_encode($fileContent)
            ];
        }
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
file_put_contents(__DIR__ . '/become_a_advisor_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Response
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'leadconnector' => $lcHttpCode,
    'bitrix' => $bitrixHttpCode,
    'bitrix_response' => json_decode($bitrixResponse, true)
]);
