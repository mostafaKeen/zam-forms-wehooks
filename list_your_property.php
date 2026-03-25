<?php
/**
 * List Your Property Webhook
 * 
 * Receives data via POST, stores it locally in a JSON file, 
 * and forwards it to LeadConnector without modification.
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

// 4. Create Lead in Bitrix24
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.lead.add.json';

// Mapping incoming data (Based on the structure provided)
$fields = $data['fields'] ?? [];

$name = $fields['name']['value'] ?? 'List Your Property Lead';
$phone = $fields['email']['value'] ?? '';           // "Phone Number*" label uses id "email"
$email = $fields['field_f4c004c']['value'] ?? '';  // "Email Address *"
$propType = strtolower($fields['field_70a4eab']['value'] ?? ''); // "Property Type *"
$community = $fields['field_d91170a']['value'] ?? ''; // "Community / Area *"
$price = $fields['field_03c4f85']['value'] ?? 0;        // "Asking price (AED)*"
$contactMethod = $fields['field_5d714be']['value'] ?? ''; // "Preferred Contact Method"
$description = $fields['field_3d9232c']['value'] ?? '';   // "Property Description"
$fileUrl = $fields['message']['value'] ?? '';        // File attachment link

// Map Property Type (Buy/Sell/Rent) to ID from bitrix metadata
$propTypeId = '';
if ($propType == 'buy') $propTypeId = '66';
elseif ($propType == 'sell') $propTypeId = '68';
elseif ($propType == 'rent') $propTypeId = '70';

$bitrixFields = [
    'fields' => [
        'TITLE' => 'List Your Property: ' . $name,
        'NAME' => $name,
        'ADDRESS_CITY' => $community,
        'OPPORTUNITY' => $price,
        'CURRENCY_ID' => 'AED',
        'UF_CRM_1772137811136' => $propTypeId, // Lead Type (Enum)
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'ASSIGNED_BY_ID' => 1,                // Default ID (RE/MAX ZAM)
        'UF_CRM_1773862147183' => '21058',    // Subsource: "Property Listing"
        'UF_CRM_1774359455' => "Preferred Contact Method: $contactMethod\nDescription: $description", // Meta Data field
        'COMMENTS' => "Lead from Elementor List Your Property form.",
    ],
    'params' => ['REGISTER_SONET_EVENT' => 'Y']
];

if (!empty($phone)) {
    $bitrixFields['fields']['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
}
if (!empty($email)) {
    $bitrixFields['fields']['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
}

// Download file and convert to Base64 if needed
if (!empty($fileUrl) && filter_var($fileUrl, FILTER_VALIDATE_URL)) {
    $fileData = file_get_contents($fileUrl);
    if ($fileData) {
        $fileName = basename($fileUrl);
        $bitrixFields['fields']['UF_CRM_1774358489'] = [
            'fileData' => [$fileName, base64_encode($fileData)]
        ];
    }
}

$chBitrix = curl_init($bitrixUrl);
curl_setopt($chBitrix, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBitrix, CURLOPT_POST, true);
curl_setopt($chBitrix, CURLOPT_POSTFIELDS, http_build_query($bitrixFields));

$bitrixResponse = curl_exec($chBitrix);
$bitrixCode = curl_getinfo($chBitrix, CURLINFO_HTTP_CODE);
$bitrixError = curl_error($chBitrix);
curl_close($chBitrix);

// 5. Log Results
$logStatus = date('[Y-m-d H:i:s] ') . "LeadConnector: $httpCode | Bitrix: $bitrixCode | Assigned To: 1";
if ($bitrixError) $logStatus .= " | Bitrix Error: $bitrixError";
file_put_contents(__DIR__ . '/list_your_property_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Respond to the sender
if ($httpCode < 400 && $bitrixCode < 400) {
    echo "Data processed and forwarded successfully.";
} else {
    http_response_code(500);
    echo "Processing completed with status - LeadConnector: $httpCode, Bitrix: $bitrixCode";
}
