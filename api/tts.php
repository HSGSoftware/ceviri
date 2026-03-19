<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

$in    = json_decode(file_get_contents('php://input'), true) ?? [];
$text  = trim($in['text'] ?? '');
$lang  = $in['lang'] ?? 'en';

if (!$text) jsonResponse(['error' => 'Metin boş'], 400);

$model = ($lang === 'ar')
    ? 'playai-tts-arabic'
    : getSetting('tts_model', 'playai-tts');

$voice = ($lang === 'ar')
    ? getSetting('tts_voice_ar', 'Ahmad-PlayAI')
    : getSetting('tts_voice', 'Fritz-PlayAI');

$audio = groqTTS($text, $model, $voice);

if ($audio === false) {
    jsonResponse(['error' => 'TTS başarısız'], 500);
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($audio));
header('Cache-Control: no-store');
echo $audio;
exit;
