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

function groqApiKey(): string
{
    $k = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
    return is_string($k) ? trim($k) : '';
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
    $addr = $_SERVER['SERVER_ADDR'] ?? '';
    if (is_string($addr) && $addr !== '' && $addr !== '127.0.0.1') {
        return $addr;
    }
    $out = @shell_exec('hostname -I 2>/dev/null');
    if (is_string($out)) {
        $ips = preg_split('/\s+/', trim($out));
        foreach ($ips ?: [] as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && $ip !== '127.0.0.1') {
                return $ip;
            }
        }
    }
    return '127.0.0.1';
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
