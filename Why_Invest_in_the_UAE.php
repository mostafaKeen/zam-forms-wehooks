<?php
/**
 * Why Invest in the UAE Webhook
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

// 2. Local Logging (Store in Why_Invest_in_the_UAE.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/Why_Invest_in_the_UAE.json', $logEntry, FILE_APPEND);

// 3. Create Lead in Bitrix24
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.lead.add.json';

// Mapping
$name = $data['Full_Name'] ?? 'UAE Investment Lead';
$phone = $data['Phone_Number'] ?? '';
$email = $data['Email'] ?? '';

// Collect other fields for meta data
$metaDataArr = $data;
unset($metaDataArr['Full_Name'], $metaDataArr['Phone_Number'], $metaDataArr['Email']);
$metaDataStr = "";
foreach ($metaDataArr as $key => $val) {
    if (is_array($val)) $val = json_encode($val);
    $metaDataStr .= "$key: $val\n";
}

$bitrixFields = [
    'fields' => [
        'TITLE' => 'UAE Investment Lead: ' . $name,
        'NAME' => $name,
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'ASSIGNED_BY_ID' => 1,                // Default ID (RE/MAX ZAM)
        'UF_CRM_1773862147183' => '21060',    // Subsource: "UAE Investment Lead"
        'UF_CRM_1774359455' => trim($metaDataStr), // Meta Data field
        'COMMENTS' => "Lead from 'Why Invest in the UAE' form.",
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
$logStatus = date('[Y-m-d H:i:s] ') . "Bitrix Status: $bitrixCode | Assigned To: 1";
file_put_contents(__DIR__ . '/Why_Invest_in_the_UAE_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Respond to the sender
echo "Data received and processed successfully.";
