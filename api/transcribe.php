<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

loadEnv(dirname(__DIR__) . '/.env');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'method_not_allowed'], 405);
}

$key = groqApiKey();
if ($key === '') {
    jsonResponse(['error' => 'missing_api_key'], 503);
}

if (!isset($_FILES['audio']) || !is_uploaded_file($_FILES['audio']['tmp_name'])) {
    jsonResponse(['error' => 'no_audio'], 400);
}

$tmp = $_FILES['audio']['tmp_name'];
$lang = isset($_POST['language']) ? preg_replace('/[^a-z]/i', '', (string) $_POST['language']) : '';
$lang = strtolower(substr($lang, 0, 5));

$mime = mime_content_type($tmp);
if (!is_string($mime)) {
    $mime = 'application/octet-stream';
}

if (!str_starts_with($mime, 'audio/') && $mime !== 'video/webm') {
    jsonResponse(['error' => 'bad_mime', 'mime' => $mime], 400);
}

$cf = new CURLFile($tmp, $mime, 'clip.webm');
$post = [
    'file' => $cf,
    'model' => 'whisper-large-v3',
    'response_format' => 'json',
];
if ($lang !== '') {
    $post['language'] = $lang;
}

$ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
    ],
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);

$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $code >= 400) {
    jsonResponse(['error' => 'groq_transcribe', 'status' => $code, 'body' => $body], 502);
}

$data = json_decode($body, true);
if (!is_array($data) || !isset($data['text'])) {
    jsonResponse(['error' => 'bad_response'], 502);
}

jsonResponse(['text' => trim((string) $data['text'])]);
