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

$raw = file_get_contents('php://input');
$in = json_decode($raw ?: 'null', true);
if (!is_array($in)) {
    jsonResponse(['error' => 'invalid_json'], 400);
}

$text = isset($in['text']) ? trim((string) $in['text']) : '';
$from = isset($in['from']) ? strtolower(preg_replace('/[^a-z]/', '', (string) $in['from'])) : '';
$to = isset($in['to']) ? strtolower(preg_replace('/[^a-z]/', '', (string) $in['to'])) : '';

if ($text === '' || strlen($text) > 8000) {
    jsonResponse(['error' => 'bad_text'], 400);
}
if ($from === '' || $to === '' || $from === $to) {
    jsonResponse(['text' => $text]);
}

$langNames = [
    'tr' => 'Turkish', 'en' => 'English', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish',
    'ar' => 'Arabic', 'it' => 'Italian', 'nl' => 'Dutch', 'pl' => 'Polish', 'pt' => 'Portuguese',
    'ru' => 'Russian', 'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese', 'hi' => 'Hindi',
    'sv' => 'Swedish', 'no' => 'Norwegian', 'da' => 'Danish', 'fi' => 'Finnish', 'cs' => 'Czech',
    'el' => 'Greek', 'he' => 'Hebrew', 'uk' => 'Ukrainian', 'ro' => 'Romanian', 'hu' => 'Hungarian',
];

$targetName = $langNames[$to] ?? $to;
$sourceName = $langNames[$from] ?? $from;

$system = 'You are a translator. Translate the user message from ' . $sourceName . ' to ' . $targetName . '. '
    . 'Output only the translation, no quotes, no explanation.';

$payload = json_encode([
    'model' => 'llama-3.3-70b-versatile',
    'messages' => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $text],
    ],
    'temperature' => 0.2,
    'max_tokens' => 2048,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);

$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $code >= 400) {
    jsonResponse(['error' => 'groq_translate', 'status' => $code], 502);
}

$data = json_decode($body, true);
if (!is_array($data)) {
    jsonResponse(['error' => 'bad_response'], 502);
}

$choice = $data['choices'][0]['message']['content'] ?? null;
if (!is_string($choice)) {
    jsonResponse(['error' => 'empty_translation'], 502);
}

jsonResponse(['text' => trim($choice)]);
