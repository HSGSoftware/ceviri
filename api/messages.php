<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$sessionId = $_GET['session_id'] ?? '';
$since     = (int)($_GET['since'] ?? 0);

if (!$sessionId) jsonResponse(['error' => 'session_id gerekli'], 400);

$db   = getDB();
$stmt = $db->prepare("
    SELECT id, speaker, original_text, translated_text, original_lang, target_lang, created_at
    FROM messages
    WHERE session_id = :sid AND id > :since
    ORDER BY id ASC
    LIMIT 50
");
$stmt->bindValue(':sid',   $sessionId, SQLITE3_TEXT);
$stmt->bindValue(':since', $since,     SQLITE3_INTEGER);
$res = $stmt->execute();

$messages = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $messages[] = $row;
}

jsonResponse(['messages' => $messages]);
