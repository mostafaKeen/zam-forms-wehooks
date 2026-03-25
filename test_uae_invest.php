<?php
/**
 * Test script for Why Invest in the UAE webhook
 */

$webhookUrl = 'http://localhost:8000/Why_Invest_in_the_UAE.php';

$payload = [
    "Full_Name" => "Mostafa Osama Test",
    "Email" => "mostafa_test@keenenter.com",
    "Phone_Number" => "+201129274930",
    "Date" => date("F d, Y"),
    "Time" => date("g:i a"),
    "Page_URL" => "https://remaxzam.ae/investor-guides/uae-investments/",
    "form_id" => "9d9202d",
    "form_name" => "New Form Test"
];

echo "Sending test payload to $webhookUrl...\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: $response\n";
