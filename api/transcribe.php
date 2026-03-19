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
$model    = getSetting('stt_model', 'whisper-large-v3-turbo');

// distil-whisper yalnızca İngilizce destekler — başka dil seçilmişse otomatik geç
if ($model === 'distil-whisper-large-v3-en' && $language !== 'en' && $language !== 'auto') {
    $model = 'whisper-large-v3-turbo';
}

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
    'response_format' => 'verbose_json', // returns detected_language too
];

// Pass language hint only when explicitly set (not 'auto')
if ($language && $language !== 'auto') {
    $postData['language'] = $language;
}

$result = groqPost('audio/transcriptions', $postData, true);
@unlink($newPath);

if (isset($result['error'])) {
    jsonResponse(['error' => $result['error']], 500);
}

$text          = trim($result['text'] ?? '');
$detectedLang  = $result['language'] ?? $language;

jsonResponse([
    'text'          => $text,
    'detected_lang' => $detectedLang,
    'model_used'    => $model,
]);
