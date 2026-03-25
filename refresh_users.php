<?php
/**
 * Bitrix24 User Fetcher
 * 
 * Fetches all active users from Bitrix24 and stores them in bitrix_users.json.
 */

$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/user.get.json';
$targetFile = __DIR__ . '/bitrix_users.json';

echo "Fetching users from Bitrix24...\n";

$allUsers = [];
$start = 0;

do {
    $url = $bitrixUrl . '?start=' . $start;
    $response = file_get_contents($url);
    if ($response === false) {
        die("Error fetching from Bitrix API.\n");
    }

    $result = json_decode($response, true);
    if (isset($result['result'])) {
        $allUsers = array_merge($allUsers, $result['result']);
    }

    if (isset($result['next'])) {
        $start = $result['next'];
    } else {
        $start = null;
    }
} while ($start !== null);

$finalData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total' => count($allUsers),
    'result' => $allUsers
];

if (file_put_contents($targetFile, json_encode($finalData, JSON_PRETTY_PRINT))) {
    echo "Successfully stored " . count($allUsers) . " users in $targetFile.\n";
} else {
    echo "Error saving to $targetFile.\n";
}
