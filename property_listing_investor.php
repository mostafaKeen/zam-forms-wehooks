<?php
/**
 * Property Listing Investor Lead Webhook Implementation
 * 
 * Captures data and stores it in property_listing_investor.json.
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

// 2. Local Logging (Store in property_listing_investor.json)
$logEntry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $data ?: $rawData
]) . PHP_EOL;

file_put_contents(__DIR__ . '/property_listing_investor.json', $logEntry, FILE_APPEND);

// 3. Bitrix24 Integration
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/';

// Mapping incoming data (handles both root and nested structures)
$innerData = isset($data['Enter_Your_Name']) ? $data : ($data['data'] ?? $data);

$name = $innerData['Enter_Your_Name'] ?? 'Investor Lead';
$email = $innerData['Enter_your_email'] ?? '';
$phone = $innerData['Enter_your_phone_number'] ?? '';
$budget = $innerData['What_is_your_budget?_____________________________'] ?? '';
$howSoon = $innerData['How_Soon_You\'re__Looking_to_Invest?'] ?? '';
$propertyInfo = $innerData['Property_Single_&#8211;_Georgia'] ?? '';
$responsibleName = trim($innerData['No_Label_field_c859e0c'] ?? '');

// Match Responsible Person
$responsibleId = 1; // Default ID (RE/MAX ZAM)
$foundMatch = false;

$userCacheFile = __DIR__ . '/bitrix_users.json';
if (file_exists($userCacheFile)) {
    $userCache = json_decode(file_get_contents($userCacheFile), true);
    if (isset($userCache['result'])) {
        // Prepare list of normalized user names for matching
        $userNames = [];
        foreach ($userCache['result'] as $user) {
            $fullName = preg_replace('/\s+/', ' ', strtolower(trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''))));
            if (!empty($fullName)) {
                $userNames[$fullName] = $user['ID'];
            }
        }

        // 1. Try Primary Key first
        if (!empty($responsibleName)) {
            $searchName = preg_replace('/\s+/', ' ', strtolower(trim($responsibleName)));
            if (isset($userNames[$searchName])) {
                $responsibleId = $userNames[$searchName];
                $foundMatch = true;
            } else {
                // Partial match fallback for primary key
                foreach ($userNames as $name => $uid) {
                    if (strpos($name, $searchName) !== false || strpos($searchName, $name) !== false) {
                        $responsibleId = $uid;
                        $foundMatch = true;
                        break;
                    }
                }
            }
        }

        // 2. Universal Search: If no match yet, check ALL other fields for any user name (Starting from the END)
        if (!$foundMatch) {
            $reversedData = array_reverse($innerData, true);
            $excludeFromSearch = [
                'Enter_Your_Name', 'Enter_your_email', 'Enter_your_phone_number',
                'name', 'email', 'phone', 'Full Name', 'Email', 'Phone Number',
                'timestamp', 'Date', 'Time', 'Page_URL', 'User_Agent', 'Remote_IP', 'Powered_by', 'form_id', 'form_name'
            ];
            
            foreach ($reversedData as $key => $value) {
                // Skip lead-specific fields and empty values
                if (in_array($key, $excludeFromSearch) || empty($value) || is_array($value)) continue;

                $valNormalized = preg_replace('/\s+/', ' ', strtolower(trim($value)));
                if (isset($userNames[$valNormalized])) {
                    $responsibleId = $userNames[$valNormalized];
                    $responsibleName = $value; // Update name for logging
                    $foundMatch = true;
                    break;
                }
            }
        }
    }
}

// Map everything else to meta data
$metaLines = [];
foreach ($innerData as $key => $value) {
    if (!in_array($key, ['Enter_Your_Name', 'Enter_your_email', 'Enter_your_phone_number', 'No_Label_field_c859e0c'])) {
        $metaLines[] = "$key: $value";
    }
}
$metaDataString = implode("\n", $metaLines);

$bitrixFields = [
    'fields' => [
        'TITLE' => 'Property Investor: ' . $name,
        'NAME' => $name,
        'OPPORTUNITY' => $budget,
        'CURRENCY_ID' => 'AED',
        'ASSIGNED_BY_ID' => $responsibleId,
        'SOURCE_ID' => 'CALLBACK',            // "Zamprime Website"
        'UF_CRM_1773862147183' => '21064',    // Subsource: "Investor Lead"
        'UF_CRM_1774359455' => $metaDataString, // meta data field
        'COMMENTS' => "Lead from 'Property Listing Investor' form.\nProperty: $propertyInfo\nTimeline: $howSoon",
    ],
    'params' => ['REGISTER_SONET_EVENT' => 'Y']
];

if (!empty($phone)) {
    $bitrixFields['fields']['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
}
if (!empty($email)) {
    $bitrixFields['fields']['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
}

$chBitrix = curl_init($bitrixUrl . 'crm.lead.add.json');
curl_setopt($chBitrix, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBitrix, CURLOPT_POST, true);
curl_setopt($chBitrix, CURLOPT_POSTFIELDS, http_build_query($bitrixFields));

$bitrixResponse = curl_exec($chBitrix);
$bitrixHttpCode = curl_getinfo($chBitrix, CURLINFO_HTTP_CODE);
curl_close($chBitrix);

// 4. Log Results
$logStatus = date('[Y-m-d H:i:s] ') . "Bitrix: $bitrixHttpCode | Assigned To: $responsibleId ($responsibleName)";
file_put_contents(__DIR__ . '/property_listing_investor_forward_log.txt', $logStatus . PHP_EOL, FILE_APPEND);

// Response
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'bitrix' => $bitrixHttpCode,
    'assigned_to' => $responsibleId
]);
