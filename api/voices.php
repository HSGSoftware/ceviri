<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$apiKey = getSetting('minimax_api_key');
if (empty($apiKey)) jsonResponse(['error' => 'MiniMax API anahtarı ayarlanmamış'], 400);

$ch = curl_init('https://api.minimax.io/v1/get_voice');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode(['voice_type' => 'preset']),
]);

$response = curl_exec($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) jsonResponse(['error' => 'cURL: ' . $curlErr], 500);

$decoded = json_decode($response, true) ?? [];
if ($code !== 200) {
    jsonResponse(['error' => $decoded['base_resp']['status_msg'] ?? "HTTP {$code}"], 500);
}

// Return simplified voice list
$voices = [];
foreach (($decoded['voice_list'] ?? []) as $v) {
    $voices[] = [
        'voice_id' => $v['voice_id'] ?? '',
        'name'     => $v['voice_name'] ?? ($v['voice_id'] ?? ''),
        'gender'   => $v['gender'] ?? '',
        'language' => $v['language'] ?? '',
    ];
}

jsonResponse(['voices' => $voices]);
