<?php
/**
 * Georgia Investment Lead Webhook
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

// 2. Local Logging (Store in georgia_investment.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/georgia_investment.json', $logEntry, FILE_APPEND);

// 3. Create Lead in Bitrix24
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.lead.add.json';

// Mapping
$name = $data['Enter_your_name'] ?? 'Georgia Investment Lead';
$phone = $data['Enter_your_phone_number'] ?? '';
$email = $data['Enter_your_email'] ?? '';
$budget = $data['Enter_your_budget'] ?? 0;

// Collect other fields for meta data
$metaDataArr = $data;
unset($metaDataArr['Enter_your_name'], $metaDataArr['Enter_your_phone_number'], $metaDataArr['Enter_your_email'], $metaDataArr['Enter_your_budget']);
$metaDataStr = "";
foreach ($metaDataArr as $key => $val) {
    if (is_array($val)) $val = json_encode($val);
    $metaDataStr .= "$key: $val\n";
}

$bitrixFields = [
    'fields' => [
        'TITLE' => 'Georgia Investment Lead: ' . $name,
        'NAME' => $name,
        'OPPORTUNITY' => $budget,
        'CURRENCY_ID' => 'USD',
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'UF_CRM_1773862147183' => '21062',    // Subsource: "Georgia Investment Lead"
        'UF_CRM_1774359455' => trim($metaDataStr), // Meta Data field
        'COMMENTS' => "Lead from 'Georgia Investment' form.",
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

// 4. Log Results
$logStatus = date('[Y-m-d H:i:s] ') . "Bitrix Status: $bitrixCode";
file_put_contents(__DIR__ . '/georgia_investment_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Respond to the sender
echo "Data received and processed successfully.";
