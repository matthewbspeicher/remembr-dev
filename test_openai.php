<?php

$key = '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';
$url = 'https://api.openai.com/v1/embeddings';
$data = ['model' => 'text-embedding-3-small', 'input' => 'hello'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer '.$key,
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
echo 'Response: '.substr($response, 0, 200)."\n";
