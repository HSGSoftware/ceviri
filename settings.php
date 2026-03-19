<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';

loadEnv(__DIR__ . '/.env');

$script = $_SERVER['SCRIPT_NAME'] ?? '/settings.php';
$dir = dirname($script);
$base = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : $dir;
$pathPrefix = $base === '' ? '' : $base;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = isset($_POST['groq_api_key']) ? trim((string) $_POST['groq_api_key']) : '';
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0700, true);
    }
    $sf = userSettingsPath();
    if ($key === '') {
        if (is_file($sf)) {
            @unlink($sf);
        }
    } else {
        file_put_contents($sf, json_encode(['groq_api_key' => $key], JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($sf, 0600);
    }
    header('Location: ' . $pathPrefix . '/settings.php?saved=1', true, 302);
    exit;
}

$uiLang = detectUiLang();
$I = i18n_for_lang($uiLang, i18n_strings());

$envKey = trim((string) (getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '')));
$fileKey = loadUserGroqKey();
$saved = isset($_GET['saved']);

$rtl = $uiLang === 'ar';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($uiLang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f1419">
  <title><?= htmlspecialchars($I['settings_title'], ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($pathPrefix) ?>/assets/style.css">
</head>
<body>
  <header>
    <nav class="top-nav">
      <a href="<?= htmlspecialchars($pathPrefix === '' ? '/' : $pathPrefix . '/', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($I['nav_home'], ENT_QUOTES, 'UTF-8') ?></a>
    </nav>
    <h1><?= htmlspecialchars($I['settings_title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($I['settings_intro'], ENT_QUOTES, 'UTF-8') ?></p>
  </header>

  <?php if ($saved): ?>
  <div class="share-box" style="border-color: var(--ok); color: var(--ok);"><?= htmlspecialchars($I['settings_saved'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($envKey !== ''): ?>
  <div class="share-box"><?= htmlspecialchars($I['settings_env_active'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($fileKey !== ''): ?>
  <div class="share-box"><?= htmlspecialchars($I['settings_file_active'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form class="panel settings-form" method="post" action="<?= htmlspecialchars($pathPrefix . '/settings.php', ENT_QUOTES, 'UTF-8') ?>">
    <label for="groq_api_key"><?= htmlspecialchars($I['api_key_label'], ENT_QUOTES, 'UTF-8') ?></label>
    <input type="password" id="groq_api_key" name="groq_api_key" autocomplete="off" placeholder="<?= htmlspecialchars($I['api_key_placeholder'], ENT_QUOTES, 'UTF-8') ?>">
    <p class="form-hint"><?= htmlspecialchars($I['settings_clear_hint'], ENT_QUOTES, 'UTF-8') ?></p>
    <button type="submit" class="btn btn-primary"><?= htmlspecialchars($I['save'], ENT_QUOTES, 'UTF-8') ?></button>
  </form>
</body>
</html>
