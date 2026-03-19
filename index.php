<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';

loadEnv(__DIR__ . '/.env');

$uiLang = detectUiLang();
$all = i18n_strings();
$I = i18n_for_lang($uiLang, $all);

$script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$dir = dirname($script);
$base = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : $dir;
$host = $_SERVER['HTTP_HOST'] ?? '';
$port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if ($host === '') {
    $host = getLanIp() . ($port && !in_array($port, [80, 443], true) ? ':' . $port : '');
}

$pathBase = $base === '' ? '/' : $base . '/';
$shareInfo = buildShareUrl($host, $port, $isHttps, $pathBase);
$shareUrl = $shareInfo['url'];

$hasKey = groqApiKey() !== '';

$langs = [
    ['code' => 'tr', 'label' => 'Türkçe'],
    ['code' => 'en', 'label' => 'English'],
    ['code' => 'de', 'label' => 'Deutsch'],
    ['code' => 'fr', 'label' => 'Français'],
    ['code' => 'es', 'label' => 'Español'],
    ['code' => 'it', 'label' => 'Italiano'],
    ['code' => 'nl', 'label' => 'Nederlands'],
    ['code' => 'pl', 'label' => 'Polski'],
    ['code' => 'ar', 'label' => 'العربية'],
    ['code' => 'pt', 'label' => 'Português'],
    ['code' => 'ru', 'label' => 'Русский'],
    ['code' => 'ja', 'label' => '日本語'],
    ['code' => 'ko', 'label' => '한국어'],
    ['code' => 'zh', 'label' => '中文'],
];

$defaultYour = $uiLang === 'tr' ? 'tr' : 'en';
$defaultTheir = $defaultYour === 'tr' ? 'en' : 'tr';

$appJson = json_encode([
    'apiTranscribe' => ($base === '' ? '' : $base) . '/api/transcribe.php',
    'apiTranslate' => ($base === '' ? '' : $base) . '/api/translate.php',
    'shareUrl' => $shareUrl,
    'i18n' => $I,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$rtl = $uiLang === 'ar';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($uiLang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f1419">
  <title><?= htmlspecialchars($I['title'], ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base === '' ? '' : $base) ?>/assets/style.css">
</head>
<body>
  <header>
    <nav class="top-nav">
      <a href="<?= htmlspecialchars(($base === '' ? '' : $base) . '/settings.php', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($I['nav_settings'], ENT_QUOTES, 'UTF-8') ?></a>
    </nav>
    <h1><?= htmlspecialchars($I['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($I['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
  </header>

  <?php if (!$hasKey): ?>
  <div class="share-box" style="border-color: var(--danger); color: var(--danger);">
    <?= htmlspecialchars($I['error_key'], ENT_QUOTES, 'UTF-8') ?>
  </div>
  <?php endif; ?>

  <div class="share-box">
    <?= htmlspecialchars($I['share_hint'], ENT_QUOTES, 'UTF-8') ?>
    <code id="share-link"><?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?></code>
    <button type="button" class="copy-btn" id="copy-url"><?= htmlspecialchars($I['copy'], ENT_QUOTES, 'UTF-8') ?></button>
    <?php
    $clientIp = clientLanIpForDisplay();
    if ($clientIp !== null):
    ?>
    <p class="client-ip-line"><?= htmlspecialchars($I['client_ip_hint'], ENT_QUOTES, 'UTF-8') ?> <code><?= htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8') ?></code></p>
    <?php endif; ?>
  </div>

  <div class="grid">
    <section class="panel">
      <h2><?= htmlspecialchars($I['your_text'], ENT_QUOTES, 'UTF-8') ?></h2>
      <label for="your-lang"><?= htmlspecialchars($I['your_lang'], ENT_QUOTES, 'UTF-8') ?></label>
      <select id="your-lang" <?= $hasKey ? '' : 'disabled' ?>>
        <?php foreach ($langs as $L): ?>
        <option value="<?= htmlspecialchars($L['code'], ENT_QUOTES, 'UTF-8') ?>"<?= $L['code'] === $defaultYour ? ' selected' : '' ?>><?= htmlspecialchars($L['label'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <div class="text-block empty" id="your-text">—</div>
    </section>
    <section class="panel">
      <h2><?= htmlspecialchars($I['their_text'], ENT_QUOTES, 'UTF-8') ?></h2>
      <label for="their-lang"><?= htmlspecialchars($I['their_lang'], ENT_QUOTES, 'UTF-8') ?></label>
      <select id="their-lang" <?= $hasKey ? '' : 'disabled' ?>>
        <?php foreach ($langs as $L): ?>
        <option value="<?= htmlspecialchars($L['code'], ENT_QUOTES, 'UTF-8') ?>"<?= $L['code'] === $defaultTheir ? ' selected' : '' ?>><?= htmlspecialchars($L['label'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <div class="text-block empty" id="their-text">—</div>
    </section>
  </div>

  <div class="controls">
    <button type="button" class="btn btn-primary" id="btn-start" <?= $hasKey ? '' : 'disabled' ?>><?= htmlspecialchars($I['start'], ENT_QUOTES, 'UTF-8') ?></button>
    <p class="hint"><?= htmlspecialchars($I['hold_to_talk'], ENT_QUOTES, 'UTF-8') ?></p>
    <button type="button" class="btn btn-record" id="btn-record" disabled><?= htmlspecialchars($I['record_btn'], ENT_QUOTES, 'UTF-8') ?></button>
    <div class="status" id="status"></div>
  </div>

  <footer class="hint-footer"><?= htmlspecialchars($I['auto_voice'], ENT_QUOTES, 'UTF-8') ?></footer>

  <script>window.__APP__ = <?= $appJson ?>;</script>
  <script src="<?= htmlspecialchars($base === '' ? '' : $base) ?>/assets/app.js" defer></script>
</body>
</html>
