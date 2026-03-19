<?php

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k !== '') {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

function userSettingsPath(): string
{
    return __DIR__ . '/data/settings.json';
}

function loadUserGroqKey(): string
{
    $p = userSettingsPath();
    if (!is_readable($p)) {
        return '';
    }
    $raw = @file_get_contents($p);
    if ($raw === false || $raw === '') {
        return '';
    }
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        return '';
    }
    $k = $j['groq_api_key'] ?? '';
    return is_string($k) ? trim($k) : '';
}

function groqApiKey(): string
{
    $k = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
    $k = is_string($k) ? trim($k) : '';
    if ($k !== '') {
        return $k;
    }
    return loadUserGroqKey();
}

function lanIpScore(string $ip): int
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return str_contains($ip, ':') && $ip !== '::1' ? 1 : 0;
    }
    if (preg_match('/^192\.168\./', $ip)) {
        return 300;
    }
    if (preg_match('/^10\./', $ip)) {
        return 200;
    }
    if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip)) {
        return 100;
    }
    return 50;
}

function httpHostWithoutPort(string $host): string
{
    $host = trim($host);
    if ($host === '') {
        return '';
    }
    if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $host, $m)) {
        return strtolower($m[1]);
    }
    if (preg_match('/^([^:]+):(\d+)$/', $host, $m) && substr_count($host, ':') === 1) {
        return strtolower($m[1]);
    }
    return strtolower($host);
}

function isLoopbackHttpHost(string $host): bool
{
    $h = httpHostWithoutPort($host);
    if ($h === '' || $h === 'localhost' || $h === '127.0.0.1' || $h === '::1' || $h === '0:0:0:0:0:0:0:1') {
        return true;
    }
    return str_starts_with($h, '127.');
}

/**
 * @return array{url: string, lanIp: string}
 */
function buildShareUrl(string $httpHost, int $serverPort, bool $isHttps, string $pathBase): array
{
    $pathBase = $pathBase === '' ? '/' : $pathBase;
    if ($pathBase[0] !== '/') {
        $pathBase = '/' . $pathBase;
    }
    $lanIp = getLanIp();

    if ($httpHost === '' || isLoopbackHttpHost($httpHost)) {
        $ps = ($serverPort && !in_array($serverPort, [80, 443], true)) ? ':' . $serverPort : '';
        return [
            'url' => 'http://' . $lanIp . $ps . $pathBase,
            'lanIp' => $lanIp,
        ];
    }

    $proto = $isHttps ? 'https' : 'http';
    if (preg_match('/^.+:\d+$/', $httpHost)) {
        return [
            'url' => $proto . '://' . $httpHost . $pathBase,
            'lanIp' => $lanIp,
        ];
    }
    $ps = ($serverPort && !in_array($serverPort, [80, 443], true)) ? ':' . $serverPort : '';
    return [
        'url' => $proto . '://' . $httpHost . $ps . $pathBase,
        'lanIp' => $lanIp,
    ];
}

function detectUiLang(): string
{
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (!is_string($accept) || $accept === '') {
        return 'en';
    }
    $parts = explode(',', $accept);
    foreach ($parts as $part) {
        $part = trim(explode(';', $part)[0]);
        $part = strtolower($part);
        if ($part === '') {
            continue;
        }
        $base = explode('-', $part)[0];
        if (in_array($base, ['tr', 'en', 'de', 'fr', 'es', 'ar', 'it', 'nl', 'pl'], true)) {
            return $base;
        }
    }
    return 'en';
}

function getLanIp(): string
{
    $candidates = [];
    $addr = $_SERVER['SERVER_ADDR'] ?? '';
    if (is_string($addr) && $addr !== '' && $addr !== '127.0.0.1' && $addr !== '::1' && $addr !== '0.0.0.0') {
        $candidates[] = $addr;
    }
    $out = @shell_exec('hostname -I 2>/dev/null');
    if (is_string($out)) {
        foreach (preg_split('/\s+/', trim($out)) ?: [] as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && $ip !== '127.0.0.1' && $ip !== '::1') {
                $candidates[] = $ip;
            }
        }
    }
    if ($candidates === []) {
        return '127.0.0.1';
    }
    usort($candidates, static function (string $a, string $b): int {
        return lanIpScore($b) <=> lanIpScore($a);
    });
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
    }
    return $candidates[0];
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
