<?php
/**
 * General Contact Webhook Implementation
 * (Previously brochure_download.php)
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

// 2. Local Logging (Store in general_contact.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/general_contact.json', $logEntry, FILE_APPEND);

// 3. Forward to LeadConnector
$targetUrl = 'https://services.leadconnectorhq.com/hooks/rszL6rWgEgcbEK6oPE0K/webhook-trigger/7U9XZmdut4iPJB35eOqF';

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
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.lead.add.json';

// Mapping incoming data
$formFields = $data['data']['fields'] ?? $data['fields'] ?? [];
$formMeta = $data['data']['meta'] ?? $data['meta'] ?? [];

$name = $formFields['name']['value'] ?? 'General Contact Lead';
$phone = $formFields['email']['value'] ?? '';    // form ID 'email' is actually Phone
$email = $formFields['field_c502fdc']['value'] ?? ''; // form ID 'field_c502fdc' is Email
$message = $formFields['message']['value'] ?? '';

// Collect unmapped fields into meta data string
$metaLines = [];
$mappedKeys = ['name', 'email', 'field_c502fdc', 'message'];

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
    'fields' => [
        'TITLE' => 'General Contact: ' . $name,
        'NAME' => $name,
        'COMMENTS' => $message,
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'ASSIGNED_BY_ID' => 1,                // Default ID (RE/MAX ZAM)
        'UF_CRM_1773862147183' => '21070',    // Subsource: "General Contact"
        'UF_CRM_1774359455' => $metaDataString, // meta data field
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
$bitrixHttpCode = curl_getinfo($chBitrix, CURLINFO_HTTP_CODE);
curl_close($chBitrix);

// 5. Log Results
$logStatus = date('[Y-m-d H:i:s] ') . "LeadConnector: $lcHttpCode | Bitrix: $bitrixHttpCode | Assigned To: 1";
file_put_contents(__DIR__ . '/general_contact_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Response
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'leadconnector' => $lcHttpCode,
    'bitrix' => $bitrixHttpCode
]);
