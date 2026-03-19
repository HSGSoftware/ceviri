<?php
declare(strict_types=1);

if (!class_exists('SQLite3')) {
    die('PHP SQLite3 extension is required.');
}
if (!function_exists('curl_init')) {
    die('PHP cURL extension is required.');
}

define('DB_DIR', __DIR__ . '/db');
define('DB_PATH', DB_DIR . '/app.sqlite');

function getDB(): SQLite3 {
    static $db = null;
    if ($db === null) {
        if (!is_dir(DB_DIR)) {
            mkdir(DB_DIR, 0755, true);
        }
        $db = new SQLite3(DB_PATH);
        $db->busyTimeout(5000);
        $db->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");
        initDB($db);
    }
    return $db;
}

function initDB(SQLite3 $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS sessions (
            id         TEXT PRIMARY KEY,
            created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
            host_lang  TEXT NOT NULL DEFAULT 'tr',
            guest_lang TEXT NOT NULL DEFAULT 'en'
        );
        CREATE TABLE IF NOT EXISTS messages (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id      TEXT    NOT NULL,
            speaker         TEXT    NOT NULL CHECK(speaker IN ('host','guest')),
            original_text   TEXT    NOT NULL,
            translated_text TEXT    NOT NULL,
            original_lang   TEXT    NOT NULL,
            target_lang     TEXT    NOT NULL,
            created_at      INTEGER NOT NULL DEFAULT (strftime('%s','now'))
        );
        CREATE INDEX IF NOT EXISTS idx_msg_session ON messages(session_id, id);
    ");
}

function getSetting(string $key, string $default = ''): string {
    $db   = getDB();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :k");
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['value'] : $default;
}

function setSetting(string $key, string $value): void {
    $db   = getDB();
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)");
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

function getLocalIP(): string {
    $out = @shell_exec("hostname -I 2>/dev/null");
    if ($out) {
        foreach (explode(' ', trim($out)) as $part) {
            $part = trim($part);
            if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && !in_array($part, ['127.0.0.1', '0.0.0.0'], true)) {
                return $part;
            }
        }
    }
    $out = @shell_exec("ip route get 8.8.8.8 2>/dev/null | awk '{print $7; exit}'");
    if ($out && filter_var(trim($out), FILTER_VALIDATE_IP)) {
        return trim($out);
    }
    if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '::1') {
        return $_SERVER['SERVER_ADDR'];
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        $h = explode(':', $_SERVER['HTTP_HOST'])[0];
        if (filter_var($h, FILTER_VALIDATE_IP)) return $h;
    }
    return 'localhost';
}

function getBaseURL(): string {
    $ip   = getLocalIP();
    $port = (int)($_SERVER['SERVER_PORT'] ?? 80);
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return ($port === 80) ? "http://{$ip}{$path}" : "http://{$ip}:{$port}{$path}";
}

function generateId(): string {
    return bin2hex(random_bytes(6));
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function groqPost(string $endpoint, array $data, bool $multipart = false): array {
    $apiKey = getSetting('groq_api_key');
    if (empty($apiKey)) {
        return ['error' => 'API anahtarı ayarlanmamış'];
    }
    $ch = curl_init('https://api.groq.com/openai/v1/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $headers = ['Authorization: Bearer ' . $apiKey];
    if ($multipart) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } else {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => 'cURL: ' . $err];
    $decoded = json_decode($response, true) ?? [];
    if ($code !== 200) {
        return ['error' => $decoded['error']['message'] ?? "HTTP {$code}"];
    }
    return $decoded;
}

function groqTTS(string $text, string $model, string $voice): string|false {
    $apiKey = getSetting('groq_api_key');
    if (empty($apiKey)) return false;
    $ch = curl_init('https://api.groq.com/openai/v1/audio/speech');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'           => $model,
            'input'           => $text,
            'voice'           => $voice,
            'response_format' => 'mp3',
        ]),
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200) ? $response : false;
}

const LANGUAGES = [
    'tr' => 'Türkçe',
    'en' => 'English',
    'de' => 'Deutsch',
    'fr' => 'Français',
    'es' => 'Español',
    'it' => 'Italiano',
    'pt' => 'Português',
    'ru' => 'Русский',
    'ar' => 'العربية',
    'zh' => '中文',
    'ja' => '日本語',
    'ko' => '한국어',
];

const LANG_NAMES_EN = [
    'tr' => 'Turkish',  'en' => 'English',  'de' => 'German',
    'fr' => 'French',   'es' => 'Spanish',  'it' => 'Italian',
    'pt' => 'Portuguese','ru' => 'Russian', 'ar' => 'Arabic',
    'zh' => 'Chinese',  'ja' => 'Japanese', 'ko' => 'Korean',
];

const STT_MODELS = [
    'whisper-large-v3-turbo' => 'Whisper Large v3 Turbo (Önerilen)',
    'whisper-large-v3'       => 'Whisper Large v3',
    'distil-whisper-large-v3-en' => 'Distil Whisper (Sadece İngilizce)',
];

const TRANSLATION_MODELS = [
    'llama-3.3-70b-versatile'  => 'Llama 3.3 70B (Önerilen)',
    'llama-3.1-8b-instant'     => 'Llama 3.1 8B (Hızlı)',
    'mixtral-8x7b-32768'       => 'Mixtral 8x7B',
    'gemma2-9b-it'             => 'Gemma 2 9B',
];

const TTS_VOICES = [
    'playai-tts' => [
        'Fritz-PlayAI'    => 'Fritz (Erkek)',
        'Ariana-PlayAI'   => 'Ariana (Kadın)',
        'Brianna-PlayAI'  => 'Brianna (Kadın)',
        'Cillian-PlayAI'  => 'Cillian (Erkek)',
        'Gus-PlayAI'      => 'Gus (Erkek)',
        'Mikail-PlayAI'   => 'Mikail (Erkek)',
        'Quinn-PlayAI'    => 'Quinn (Nötr)',
    ],
    'playai-tts-arabic' => [
        'Ahmad-PlayAI'  => 'Ahmad (Erkek)',
        'Nadia-PlayAI'  => 'Nadia (Kadın)',
        'Amira-PlayAI'  => 'Amira (Kadın)',
    ],
];
