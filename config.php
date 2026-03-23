<?php
declare(strict_types=1);

// Prevent PHP errors/warnings from corrupting JSON responses
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false;
    error_log("[{$errno}] {$errstr} in {$errfile}:{$errline}");
    return true;
});

set_exception_handler(function(\Throwable $e): void {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

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
    // 1. Manuel ayarlanan URL (Cloudflare Tunnel vb.) her şeyin önünde gelir
    $custom = getSetting('app_base_url');
    if (!empty($custom)) {
        return rtrim($custom, '/');
    }

    // 2. stunnel/nginx HTTPS proxy aktifse
    $httpsPort = getenv('LOCALTALK_HTTPS_PORT');
    $httpsOk   = getenv('LOCALTALK_HTTPS_OK');
    if ($httpsPort && $httpsOk === 'true') {
        $p = (int)$httpsPort;
        $ip = getLocalIP();
        return ($p === 443) ? "https://{$ip}" : "https://{$ip}:{$p}";
    }

    // 3. Düz HTTP (lokal ağ)
    $ip   = getLocalIP();
    $port = (int)($_SERVER['SERVER_PORT'] ?? 80);
    return ($port === 80) ? "http://{$ip}" : "http://{$ip}:{$port}";
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

// Returns MP3 binary data on success, or ['error' => '...'] on failure
function openaiSTT(string $filePath, string $mime, string $ext, string $model, string $language, string $prompt = ''): array {
    $apiKey = getSetting('openai_api_key');
    if (empty($apiKey)) return ['error' => 'OpenAI API anahtarı ayarlanmamış'];

    $postData = [
        'file'            => new CURLFile($filePath, $mime, 'audio.' . $ext),
        'model'           => $model,
        'response_format' => 'verbose_json',
    ];
    if ($language && $language !== 'auto') {
        $postData['language'] = $language;
    }
    if ($prompt) {
        $postData['prompt'] = $prompt;
    }

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS     => $postData,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['error' => 'cURL: ' . $curlErr];
    $decoded = json_decode($response, true) ?? [];
    if ($code !== 200) {
        return ['error' => $decoded['error']['message'] ?? "HTTP {$code}"];
    }
    return $decoded;
}

function openaiChat(string $model, array $messages, float $temperature = 0.1): array {
    $apiKey = getSetting('openai_api_key');
    if (empty($apiKey)) return ['error' => 'OpenAI API anahtarı ayarlanmamış'];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => 2000,
        ]),
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr) return ['error' => 'cURL: ' . $curlErr];
    $decoded = json_decode($response, true) ?? [];
    if ($code !== 200) return ['error' => $decoded['error']['message'] ?? "HTTP {$code}"];
    return $decoded;
}

function minimaxTTS(string $text, string $model, string $voiceId, float $speed, float $vol, int $pitch): string|array {
    $apiKey = getSetting('minimax_api_key');
    if (empty($apiKey)) return ['error' => 'MiniMax API anahtarı ayarlanmamış'];

    $ch = curl_init('https://api.minimax.io/v1/t2a_v2');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'  => $model,
            'text'   => $text,
            'stream' => false,
            'voice_setting' => [
                'voice_id' => $voiceId,
                'speed'    => $speed,
                'vol'      => $vol,
                'pitch'    => $pitch,
            ],
            'audio_setting' => [
                'sample_rate' => 32000,
                'bitrate'     => 128000,
                'format'      => 'mp3',
                'channel'     => 1,
            ],
        ]),
    ]);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr)  return ['error' => 'cURL: ' . $curlErr];
    if (!$response) return ['error' => 'Boş yanıt'];

    $decoded = json_decode($response, true) ?? [];

    // Check for API-level error
    $baseCode = $decoded['base_resp']['status_code'] ?? 0;
    if ($baseCode !== 0) {
        return ['error' => $decoded['base_resp']['status_msg'] ?? "API hata kodu {$baseCode}"];
    }

    $hexAudio = $decoded['data']['audio'] ?? '';
    if (!$hexAudio) {
        return ['error' => "Ses verisi boş (HTTP {$code})"];
    }

    return hex2bin($hexAudio);
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
    'whisper-1'              => 'Whisper-1 (Önerilen)',
    'gpt-4o-transcribe'      => 'GPT-4o Transcribe (Yüksek Kalite)',
    'gpt-4o-mini-transcribe' => 'GPT-4o Mini Transcribe (Hızlı)',
];

const TRANSLATION_MODELS = [
    'gpt-4o-mini'  => 'GPT-4o Mini (Hızlı, Önerilen)',
    'gpt-4o'       => 'GPT-4o (Yüksek Kalite)',
    'gpt-4.1-mini' => 'GPT-4.1 Mini',
    'gpt-4.1'      => 'GPT-4.1',
    'gpt-4-turbo'  => 'GPT-4 Turbo',
    'o1-mini'      => 'o1 Mini (Akıl Yürütme)',
    'o3-mini'      => 'o3 Mini',
];

const MINIMAX_MODELS = [
    'speech-02-turbo'  => 'Speech-02 Turbo (Hızlı, Önerilen)',
    'speech-02-hd'     => 'Speech-02 HD (Yüksek Kalite)',
    'speech-2.6-turbo' => 'Speech-2.6 Turbo',
    'speech-2.6-hd'    => 'Speech-2.6 HD',
    'speech-2.8-turbo' => 'Speech-2.8 Turbo (En Yeni)',
    'speech-2.8-hd'    => 'Speech-2.8 HD (En Yeni, HD)',
];

// Curated voice presets by language (voice_id => display name)
const MINIMAX_VOICE_PRESETS = [
    'tr' => [
        'Turkish_Trustworthyman' => 'Güvenilir Erkek',
        'Turkish_CalmWoman'      => 'Sakin Kadın',
    ],
    'en' => [
        'English_expressive_narrator' => 'Expressive Narrator (E)',
        'English_Booming_man'         => 'Booming Man (E)',
        'English_trustworthy_man'     => 'Trustworthy Man (E)',
        'English_cheerful_lady'       => 'Cheerful Lady (K)',
        'English_sweet_lady'          => 'Sweet Lady (K)',
    ],
    'de' => [
        'German_Booming_man'  => 'Booming Man (E)',
        'German_sweet_lady'   => 'Sweet Lady (K)',
    ],
    'fr' => [
        'French_Booming_man'  => 'Booming Man (E)',
        'French_sweet_lady'   => 'Sweet Lady (K)',
    ],
    'es' => [
        'Spanish_Booming_man' => 'Booming Man (E)',
        'Spanish_sweet_lady'  => 'Sweet Lady (K)',
    ],
    'it' => [
        'Italian_Booming_man' => 'Booming Man (E)',
        'Italian_sweet_lady'  => 'Sweet Lady (K)',
    ],
    'pt' => [
        'Portuguese_Booming_man' => 'Booming Man (E)',
        'Portuguese_sweet_lady'  => 'Sweet Lady (K)',
    ],
    'ru' => [
        'Russian_strict_woman' => 'Strict Woman (K)',
        'Russian_jovial_man'   => 'Jovial Man (E)',
    ],
    'ar' => [
        'Arabic_Booming_man'  => 'Booming Man (E)',
        'Arabic_sweet_lady'   => 'Sweet Lady (K)',
    ],
    'zh' => [
        'Chinese_Booming_man' => 'Booming Man (E)',
        'Chinese_sweet_lady'  => 'Sweet Lady (K)',
    ],
    'ja' => [
        'Japanese_sweet_lady'          => 'Sweet Lady (K)',
        'Japanese_expressive_narrator' => 'Expressive Narrator (E)',
    ],
    'ko' => [
        'Korean_sweet_lady'          => 'Sweet Lady (K)',
        'Korean_expressive_narrator' => 'Expressive Narrator (E)',
    ],
];
