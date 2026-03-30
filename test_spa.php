<?php
$becomeAdvisorJson = file_get_contents('become_a_advisor.json');
$advisorRows = explode("\n", trim($becomeAdvisorJson));
$advisorData = json_decode($advisorRows[1], true)['data']; // take the 2nd row as it has valid data

echo "Sending to become_a_advisor.php...\n";
$ch = curl_init('http://localhost:8080/become_a_advisor.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($advisorData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resp = curl_exec($ch);
echo "Response: " . $resp . "\n";
curl_close($ch);

$careersLeadJson = file_get_contents('careers_lead.json');
$careersRows = explode("\n", trim($careersLeadJson));
$careersData = json_decode($careersRows[1], true)['data'];

echo "Sending to careers_lead.php...\n";
$ch = curl_init('http://localhost:8080/careers_lead.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($careersData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resp = curl_exec($ch);
echo "Response: " . $resp . "\n";
curl_close($ch);
