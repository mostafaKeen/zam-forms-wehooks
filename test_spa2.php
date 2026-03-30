<?php
$bitrixUrl = 'https://zamprime.bitrix24.com/rest/16/bfumzpzx61oc5s6o/crm.item.add.json';

$bitrixFields = [
    'entityTypeId' => 1038,
    'fields' => [
        'title' => 'Test API Item careers lead',
        'sourceId' => 'CALLBACK',
        'assignedById' => 28,
        'ufCrm8_1772192069412' => '+201129274930', // Phone
        'ufCrm8_1772192889128' => 'test@keenenter.com', // Email
        'sourceDescription' => "Message: test message\nBrokerage: none",
        'ufCrm8_1774869698' => 'linkedin.com/in/test'
    ]
];

$chBitrix = curl_init($bitrixUrl);
curl_setopt($chBitrix, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBitrix, CURLOPT_POST, true);
curl_setopt($chBitrix, CURLOPT_POSTFIELDS, http_build_query($bitrixFields));

$bitrixResponse = curl_exec($chBitrix);
echo $bitrixResponse;
curl_close($chBitrix);
