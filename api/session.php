<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in        = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = $in['session_id'] ?? generateId();
    $hostLang  = $in['host_lang']  ?? 'tr';
    $guestLang = $in['guest_lang'] ?? 'en';

    $db   = getDB();
    $stmt = $db->prepare("INSERT OR REPLACE INTO sessions (id, host_lang, guest_lang) VALUES (:id,:hl,:gl)");
    $stmt->bindValue(':id', $sessionId, SQLITE3_TEXT);
    $stmt->bindValue(':hl', $hostLang,  SQLITE3_TEXT);
    $stmt->bindValue(':gl', $guestLang, SQLITE3_TEXT);
    $stmt->execute();
    jsonResponse(['session_id' => $sessionId, 'host_lang' => $hostLang, 'guest_lang' => $guestLang]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $in        = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = $in['session_id'] ?? '';
    $role      = $in['role']       ?? '';
    $lang      = $in['lang']       ?? '';

    if (!$sessionId || !$role || !$lang) jsonResponse(['error' => 'Eksik parametre'], 400);

    $field = ($role === 'host') ? 'host_lang' : 'guest_lang';
    $db    = getDB();
    $stmt  = $db->prepare("UPDATE sessions SET {$field} = :lang WHERE id = :id");
    $stmt->bindValue(':lang', $lang,      SQLITE3_TEXT);
    $stmt->bindValue(':id',   $sessionId, SQLITE3_TEXT);
    $stmt->execute();
    jsonResponse(['ok' => true]);
}

// GET
$sessionId = $_GET['session_id'] ?? '';
if (!$sessionId) jsonResponse(['error' => 'session_id gerekli'], 400);

$db   = getDB();
$stmt = $db->prepare("SELECT id, host_lang, guest_lang FROM sessions WHERE id = :id");
$stmt->bindValue(':id', $sessionId, SQLITE3_TEXT);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if (!$row) jsonResponse(['error' => 'Oturum bulunamadı'], 404);
jsonResponse($row);
