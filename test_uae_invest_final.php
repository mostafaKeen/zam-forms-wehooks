<?php
/**
 * Test script for UAE Investor Lead webhook
 */

$webhookUrl = 'http://localhost/keen/zam%20forms/uae_investor.php'; // Update with your local URL if needed

$payload = json_decode('{"timestamp":"2026-03-24 15:42:37","data":{"form":{"id":"7c078b5","name":"New Form"},"fields":{"name":{"id":"name","type":"text","title":"Full Name (required)","value":"Mostafa Osama Test","raw_value":"Mostafa Osama","required":"1"},"email":{"id":"email","type":"email","title":"Email Address (required)","value":"mostafa_test@keenenter.com","raw_value":"mostafa@keenenter.com","required":"1"},"phone":{"id":"phone","type":"tel","title":"Phone Number (required)","value":"+201111111111","raw_value":"+201129274930","required":"1"},"preferred_method":{"id":"preferred_method","type":"select","title":"Preferred Contact Method","value":"Email","raw_value":"Email","required":"1"},"interested_in":{"id":"interested_in","type":"radio","title":"Interested In ","value":"Investor Projects","raw_value":"Investor Projects","required":"1"},"preferred_date_time":{"id":"preferred_date_time","type":"text","title":"Preferred Date and Time","value":"test","raw_value":"test","required":"1"},"additional_notes":{"id":"additional_notes","type":"text","title":"Additional Notes (optional)","value":"testing metadata collection","raw_value":"optionnnnnnnn","required":"1"}},"meta":{"date":{"title":"Date","value":"March 24, 2026"},"time":{"title":"Time","value":"2:42 pm"},"page_url":{"title":"Page URL","value":"https:\/\/remaxzam.ae\/properties-all\/"},"user_agent":{"title":"User Agent","value":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/146.0.0.0 Safari\/537.36"},"remote_ip":{"title":"Remote IP","value":"197.38.44.135"},"credit":{"title":"Powered by","value":"PRO Elements"}}}}', true);

// Extract the 'data' part as the script expects it
$dataPart = $payload['data'];

echo "Sending test payload to $webhookUrl...\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataPart));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch) . "\n";
}
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: $response\n";
