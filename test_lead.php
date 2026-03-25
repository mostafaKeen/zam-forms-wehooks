<?php
/**
 * Test script to simulate a lead from list_your_property.json
 */

$jsonFile = 'f:/keen/zam forms/list_your_property.json';
$webhookUrl = 'http://localhost:8000/list_your_property.php';

if (!file_exists($jsonFile)) {
    die("JSON file not found.\n");
}

$lines = file($jsonFile);
$lastLine = end($lines);
$testData = json_decode($lastLine, true);

if (!$testData || !isset($testData['data'])) {
    die("Invalid JSON data in file.\n");
}

$payload = json_encode($testData['data']);

echo "Sending test payload to $webhookUrl...\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: $response\n";

if ($httpCode == 200) {
    echo "Test lead creation triggered successfully. Check list_your_property_forward_log.txt for results.\n";
} else {
    echo "Test failed.\n";
}
