<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

$in   = json_decode(file_get_contents('php://input'), true) ?? [];
$text = trim($in['text'] ?? '');
if (!$text) jsonResponse(['error' => 'Metin boş'], 400);

$engine = getSetting('tts_engine', 'webspeech');
if ($engine !== 'minimax') jsonResponse(['error' => 'TTS motoru minimax değil'], 400);

$model = getSetting('minimax_tts_model', 'speech-02-turbo');
$voice = getSetting('minimax_tts_voice', 'Turkish_Trustworthyman');
$speed = max(0.5, min(2.0, (float)(getSetting('minimax_tts_speed', '1.0') ?: 1.0)));
$vol   = max(0.1, min(10.0, (float)(getSetting('minimax_tts_vol',   '1.0') ?: 1.0)));
$pitch = max(-12, min(12,   (int)(getSetting('minimax_tts_pitch',  '0')   ?: 0)));

$result = minimaxTTS($text, $model, $voice, $speed, $vol, $pitch);

if (is_array($result)) {
    jsonResponse(['error' => $result['error']], 500);
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($result));
echo $result;
exit;
