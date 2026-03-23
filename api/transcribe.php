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

$language  = trim($_POST['language']    ?? 'auto');
$model     = getSetting('stt_model', 'whisper-1');

// JS sends explicit format (never rely solely on Content-Type which Chrome may mangle)
$postFormat = trim($_POST['audio_format'] ?? '');
$mime       = $audioFile['type'] ?: 'audio/webm';

$ext = match(true) {
    $postFormat === 'wav'                                      => 'wav',
    $postFormat === 'mp3'                                      => 'mp3',
    in_array($postFormat, ['mp4','m4a'], true)                 => 'mp4',
    $postFormat === 'ogg'                                      => 'ogg',
    str_contains($mime, 'mp4') || str_contains($mime, 'm4a')  => 'mp4',
    str_contains($mime, 'ogg')                                 => 'ogg',
    str_contains($mime, 'wav')                                 => 'wav',
    str_contains($mime, 'mpeg')                                => 'mp3',
    default                                                    => 'webm',
};

// nonverbal=1 only when JS explicitly confirmed WAV conversion succeeded
$nonVerbal = getSetting('stt_nonverbal', '1') === '1'
          && ($_POST['nonverbal'] ?? '0') === '1'
          && $ext === 'wav'; // safety: gpt-4o-audio-preview must get wav

$tmpPath = $audioFile['tmp_name'];
$newPath = $tmpPath . '.' . $ext;
rename($tmpPath, $newPath);

if ($nonVerbal) {
    $result    = openaiAudioTranscribe($newPath, $ext, $language, true);
    $modelUsed = 'gpt-4o-audio-preview';
} else {
    $result    = openaiSTT($newPath, $mime, $ext, $model, $language, '');
    $modelUsed = $model;
}

@unlink($newPath);

if (isset($result['error'])) {
    jsonResponse(['error' => $result['error']], 500);
}

jsonResponse([
    'text'          => trim($result['text'] ?? ''),
    'detected_lang' => $result['language'] ?? $language,
    'model_used'    => $modelUsed,
]);
