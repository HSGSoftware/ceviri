<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

$audioFile = $_FILES['audio'] ?? null;
if (!$audioFile || $audioFile['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Ses dosyası alınamadı: ' . ($audioFile['error'] ?? 'yok')], 400);
}

$model    = getSetting('stt_model', 'whisper-large-v3-turbo');
$language = $_POST['language'] ?? 'auto';

$mime = $audioFile['type'] ?: 'audio/webm';
$ext  = match(true) {
    str_contains($mime, 'mp4') || str_contains($mime, 'm4a') => 'mp4',
    str_contains($mime, 'ogg')  => 'ogg',
    str_contains($mime, 'wav')  => 'wav',
    str_contains($mime, 'mpeg') => 'mp3',
    default                     => 'webm',
};

$tmpPath = $audioFile['tmp_name'];
$newPath = $tmpPath . '.' . $ext;
rename($tmpPath, $newPath);

$postData = [
    'file'            => new CURLFile($newPath, $mime, 'audio.' . $ext),
    'model'           => $model,
    'response_format' => 'json',
];
if ($language && $language !== 'auto') {
    $postData['language'] = $language;
}

$result = groqPost('audio/transcriptions', $postData, true);
@unlink($newPath);

if (isset($result['error'])) jsonResponse(['error' => $result['error']], 500);
jsonResponse(['text' => trim($result['text'] ?? '')]);
