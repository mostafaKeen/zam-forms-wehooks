<?php
/**
 * Cold Reach Out Webhook
 * 
 * Receives data via POST, stores it locally in a JSON file, 
 * and forwards it to Make.com without modification.
 */

// 1. Receive data
// Captures raw POST data (common for webhooks sending JSON)
$rawData = file_get_contents('php://input');

// Fallback to $_POST if raw input is empty (for standard form-data)
if (empty($rawData) && !empty($_POST)) {
    $rawData = json_encode($_POST);
}

// If still empty, there's nothing to process
if (empty($rawData)) {
    http_response_code(400);
    echo "No data received.";
    exit;
}

// 2. Store in JSON file
$data = json_decode($rawData, true);

// If it's not JSON, it might be URL-encoded form data (query-string format)
if (is_null($data)) {
    parse_str($rawData, $data);
}

$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/cold_reach_out.json', $logEntry, FILE_APPEND);

// 3. Send to Make.com hook (Still sends raw data to maintain original format)
$targetUrl = 'https://hook.eu1.make.com/9m3wf1pbxxlqk9s3a9c2nhwwy3yje7qz';

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$makeCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. Create Lead in Bitrix24
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.lead.add.json';

/**
 * Mapping incoming data
 * When using parse_str, spaces in keys are converted to underscores.
 * "Enter your name" became "Enter_your_name" etc.
 */
$name = $data['Enter_your_name'] ?? ($data['name'] ?? ($data['Name'] ?? 'Cold Reach Out Lead'));
$phone = $data['Enter_your_phone_number'] ?? ($data['phone'] ?? ($data['Phone'] ?? ''));
$email = $data['Enter_your_email'] ?? ($data['email'] ?? ($data['Email'] ?? ''));

$bitrixFields = [
    'fields' => [
        'TITLE' => 'Cold Reach Out: ' . $name,
        'NAME' => $name,
        'ASSIGNED_BY_ID' => 1,                // Default ID (RE/MAX ZAM)
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'UF_CRM_1773862147183' => '21056',    // Subsource: "Cold Reach Out"
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
$bitrixError = curl_error($chBitrix);
curl_close($chBitrix);

// 5. Log the results
$forwardLog = date('[Y-m-d H:i:s] ') . "Make: $makeCode | Bitrix: $bitrixCode | Assigned To: 1";
if ($bitrixError) $forwardLog .= " | Bitrix Error: $bitrixError";
file_put_contents(__DIR__ . '/forwarding_log.txt', $forwardLog . PHP_EOL, FILE_APPEND);

// Respond to the sender
if ($makeCode < 400 && $bitrixCode < 400) {
    echo "Data processed successfully.";
} else {
    http_response_code(500);
    echo "Processing completed with status - Make: $makeCode, Bitrix: $bitrixCode";
}
