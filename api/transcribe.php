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

$mime = @mime_content_type($tmp);
if (!is_string($mime) || $mime === '') {
    $mime = 'application/octet-stream';
}

$head = @file_get_contents($tmp, false, null, 0, 16);
if (is_string($head) && $head !== '') {
    if (strlen($head) >= 4 && substr($head, 0, 4) === "\x1a\x45\xdf\xa3") {
        $mime = 'video/webm';
    } elseif (strlen($head) >= 8 && substr($head, 4, 4) === 'ftyp') {
        $mime = 'video/mp4';
    } elseif (str_starts_with($head, 'OggS')) {
        $mime = 'audio/ogg';
    } elseif (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE') {
        $mime = 'audio/wav';
    } elseif (str_starts_with($head, 'ID3')
        || (strlen($head) >= 2 && ord($head[0]) === 0xff && (ord($head[1]) & 0xe0) === 0xe0)) {
        $mime = 'audio/mpeg';
    }
}

$allowedVideo = ['video/webm', 'video/mp4', 'video/quicktime'];
$ok = str_starts_with($mime, 'audio/') || in_array($mime, $allowedVideo, true)
    || $mime === 'application/octet-stream';
if (!$ok) {
    jsonResponse(['error' => 'bad_mime', 'mime' => $mime], 400);
}

if ($mime === 'application/octet-stream') {
    $mime = 'audio/webm';
}

if ($mime === 'video/webm') {
    $mime = 'audio/webm';
}

$clipName = 'clip.webm';
if (str_contains($mime, 'mp4') || str_contains($mime, 'quicktime')) {
    $clipName = 'clip.mp4';
} elseif (str_contains($mime, 'wav') || str_contains($mime, 'wave')) {
    $clipName = 'clip.wav';
} elseif (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
    $clipName = 'clip.mp3';
} elseif (str_contains($mime, 'ogg')) {
    $clipName = 'clip.ogg';
}

$cf = new CURLFile($tmp, $mime, $clipName);

$models = groqTranscribeModels();
$lastCode = 0;
$lastBody = '';
$lastMsg = null;

foreach ($models as $model) {
    $post = [
        'file' => $cf,
        'model' => $model,
        'response_format' => 'json',
    ];

    [$body, $code, $cerr] = groqCurlPostMultipart(
        'https://api.groq.com/openai/v1/audio/transcriptions',
        $key,
        $post,
        120
    );

    if ($body === false || $cerr !== '') {
        jsonResponse([
            'error' => 'curl',
            'message' => $cerr !== '' ? $cerr : 'curl_exec failed',
        ], 502);
    }

    if ($code < 400) {
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['text'])) {
            jsonResponse(['text' => trim((string) $data['text'])]);
        }
        jsonResponse(['error' => 'bad_response', 'message' => groqExtractErrorMessage($body)], 502);
    }

    $lastCode = $code;
    $lastBody = is_string($body) ? $body : '';
    $lastMsg = groqExtractErrorMessage($lastBody);
}

$outCode = ($lastCode >= 400 && $lastCode < 600) ? $lastCode : 502;
jsonResponse([
    'error' => 'groq_transcribe',
    'status' => $lastCode,
    'message' => $lastMsg ?? (strlen($lastBody) > 0 ? substr($lastBody, 0, 500) : null),
], $outCode);
