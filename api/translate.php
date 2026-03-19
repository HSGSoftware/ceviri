<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

$in         = json_decode(file_get_contents('php://input'), true) ?? [];
$text       = trim($in['text']       ?? '');
$sourceLang = $in['source_lang']     ?? 'tr';
$targetLang = $in['target_lang']     ?? 'en';
$sessionId  = $in['session_id']      ?? '';
$speaker    = $in['speaker']         ?? 'host';

if (!$text)      jsonResponse(['error' => 'Metin boş'], 400);
if (!$sessionId) jsonResponse(['error' => 'session_id gerekli'], 400);

$translatedText = $text;

if ($sourceLang !== $targetLang) {
    $srcName = LANG_NAMES_EN[$sourceLang] ?? $sourceLang;
    $tgtName = LANG_NAMES_EN[$targetLang] ?? $targetLang;
    $model   = getSetting('translation_model', 'llama-3.3-70b-versatile');

    $result = groqPost('chat/completions', [
        'model'       => $model,
        'temperature' => 0.1,
        'max_tokens'  => 2000,
        'messages'    => [
            [
                'role'    => 'system',
                'content' => "You are a professional translator. Translate the text from {$srcName} to {$tgtName}. Output ONLY the translation, nothing else.",
            ],
            ['role' => 'user', 'content' => $text],
        ],
    ]);

    if (isset($result['error'])) jsonResponse(['error' => $result['error']], 500);
    $translatedText = trim($result['choices'][0]['message']['content'] ?? $text);
}

$db   = getDB();
$stmt = $db->prepare("
    INSERT INTO messages (session_id, speaker, original_text, translated_text, original_lang, target_lang)
    VALUES (:sid, :sp, :orig, :trans, :ol, :tl)
");
$stmt->bindValue(':sid',   $sessionId,     SQLITE3_TEXT);
$stmt->bindValue(':sp',    $speaker,       SQLITE3_TEXT);
$stmt->bindValue(':orig',  $text,          SQLITE3_TEXT);
$stmt->bindValue(':trans', $translatedText,SQLITE3_TEXT);
$stmt->bindValue(':ol',    $sourceLang,    SQLITE3_TEXT);
$stmt->bindValue(':tl',    $targetLang,    SQLITE3_TEXT);
$stmt->execute();

jsonResponse(['translated' => $translatedText, 'message_id' => $db->lastInsertRowID()]);
