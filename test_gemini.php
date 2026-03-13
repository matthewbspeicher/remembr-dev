<?php

$key = getenv('GEMINI_API_KEY');
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$key}";
$data = ['model' => 'models/gemini-embedding-001', 'content' => ['parts' => [['text' => 'hello']]]];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
$json = json_decode($response, true);
echo 'Dimension count: '.count($json['embedding']['values'])."\n";
