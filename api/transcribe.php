<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

$audioFile = $_FILES['audio'] ?? null;
if (!$audioFile || $audioFile['error'] !== UPLOAD_ERR_OK) {
    $errCode = $audioFile['error'] ?? -1;
    $errMsg  = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya çok büyük',
        UPLOAD_ERR_NO_FILE  => 'Ses dosyası alınamadı',
        default             => "Yükleme hatası ({$errCode})",
    };
    jsonResponse(['error' => $errMsg], 400);
}

$language = trim($_POST['language'] ?? 'auto');
$model    = getSetting('stt_model', 'whisper-1');

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

$result = openaiSTT($newPath, $mime, $ext, $model, $language);
@unlink($newPath);

if (isset($result['error'])) {
    jsonResponse(['error' => $result['error']], 500);
}

jsonResponse([
    'text'          => trim($result['text'] ?? ''),
    'detected_lang' => $result['language'] ?? $language,
    'model_used'    => $model,
]);
