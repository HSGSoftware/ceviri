<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$saved   = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey      = trim($_POST['groq_api_key']      ?? '');
    $sttModel    = $_POST['stt_model']              ?? 'whisper-large-v3-turbo';
    $transModel  = $_POST['translation_model']      ?? 'llama-3.3-70b-versatile';
    $ttsEngine   = $_POST['tts_engine']             ?? 'webspeech';
    $ttsModel    = $_POST['tts_model']              ?? 'playai-tts';
    $ttsVoice    = $_POST['tts_voice']              ?? 'Fritz-PlayAI';
    $ttsVoiceAr  = $_POST['tts_voice_ar']           ?? 'Ahmad-PlayAI';

    if (!array_key_exists($sttModel,   STT_MODELS))         $errors[] = 'Geçersiz STT modeli';
    if (!array_key_exists($transModel, TRANSLATION_MODELS)) $errors[] = 'Geçersiz çeviri modeli';

    if (empty($errors)) {
        setSetting('groq_api_key',       $apiKey);
        setSetting('stt_model',          $sttModel);
        setSetting('translation_model',  $transModel);
        setSetting('tts_engine',         $ttsEngine);
        setSetting('tts_model',          $ttsModel);
        setSetting('tts_voice',          $ttsVoice);
        setSetting('tts_voice_ar',       $ttsVoiceAr);
        $saved = true;
    }
}

$cur = [
    'groq_api_key'      => getSetting('groq_api_key'),
    'stt_model'         => getSetting('stt_model',         'whisper-large-v3-turbo'),
    'translation_model' => getSetting('translation_model', 'llama-3.3-70b-versatile'),
    'tts_engine'        => getSetting('tts_engine',        'webspeech'),
    'tts_model'         => getSetting('tts_model',         'playai-tts'),
    'tts_voice'         => getSetting('tts_voice',         'Fritz-PlayAI'),
    'tts_voice_ar'      => getSetting('tts_voice_ar',      'Ahmad-PlayAI'),
];

$localIP = getLocalIP();
$port    = $_SERVER['SERVER_PORT'] ?? 80;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0d0d18">
<title>Ayarlar – LocalTalk</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="settings-page">
<div id="app">
  <header class="header">
    <div class="header-left">
      <a href="index.php" class="back-btn">←</a>
      <span class="logo">⚙ Ayarlar</span>
    </div>
  </header>

  <main class="settings-main">
    <?php if ($saved): ?>
    <div class="alert alert-success">✓ Ayarlar kaydedildi</div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-error">✗ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" class="settings-form">

      <!-- Network info -->
      <section class="settings-section">
        <h2 class="section-title">Ağ Bilgisi</h2>
        <div class="info-row">
          <span class="info-label">Yerel IP</span>
          <span class="info-value mono"><?= htmlspecialchars($localIP) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Port</span>
          <span class="info-value mono"><?= htmlspecialchars((string)$port) ?></span>
        </div>
      </section>

      <!-- API Key -->
      <section class="settings-section">
        <h2 class="section-title">Groq API</h2>
        <div class="field">
          <label class="field-label" for="groq_api_key">API Anahtarı</label>
          <div class="input-wrap">
            <input
              type="password"
              id="groq_api_key"
              name="groq_api_key"
              class="field-input"
              value="<?= htmlspecialchars($cur['groq_api_key']) ?>"
              placeholder="gsk_..."
              autocomplete="off"
            >
            <button type="button" class="eye-btn" onclick="toggleApiKeyVisibility()">👁</button>
          </div>
          <p class="field-hint">
            <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a> adresinden alabilirsiniz
          </p>
        </div>
      </section>

      <!-- STT Model -->
      <section class="settings-section">
        <h2 class="section-title">Konuşma Tanıma (STT)</h2>
        <div class="field">
          <label class="field-label" for="stt_model">Whisper Modeli</label>
          <select id="stt_model" name="stt_model" class="field-select">
            <?php foreach (STT_MODELS as $val => $label): ?>
            <option value="<?= $val ?>" <?= $cur['stt_model'] === $val ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </section>

      <!-- Translation Model -->
      <section class="settings-section">
        <h2 class="section-title">Çeviri Modeli (LLM)</h2>
        <div class="field">
          <label class="field-label" for="translation_model">Model</label>
          <select id="translation_model" name="translation_model" class="field-select">
            <?php foreach (TRANSLATION_MODELS as $val => $label): ?>
            <option value="<?= $val ?>" <?= $cur['translation_model'] === $val ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </section>

      <!-- TTS -->
      <section class="settings-section">
        <h2 class="section-title">Seslendirme (TTS)</h2>
        <div class="field">
          <label class="field-label">Motor</label>
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" name="tts_engine" value="webspeech"
                     <?= $cur['tts_engine'] === 'webspeech' ? 'checked' : '' ?>
                     onchange="showTTSOptions(this.value)">
              Web Speech API (Tarayıcı – Ücretsiz)
            </label>
            <label class="radio-label">
              <input type="radio" name="tts_engine" value="groq"
                     <?= $cur['tts_engine'] === 'groq' ? 'checked' : '' ?>
                     onchange="showTTSOptions(this.value)">
              Groq PlayAI (Yüksek Kalite)
            </label>
          </div>
        </div>

        <div id="groqTTSOptions" style="<?= $cur['tts_engine'] === 'groq' ? '' : 'display:none' ?>">
          <div class="field">
            <label class="field-label" for="tts_model">TTS Modeli</label>
            <select id="tts_model" name="tts_model" class="field-select" onchange="updateVoices(this.value)">
              <option value="playai-tts"         <?= $cur['tts_model'] === 'playai-tts'         ? 'selected' : '' ?>>PlayAI TTS (Genel)</option>
              <option value="playai-tts-arabic"  <?= $cur['tts_model'] === 'playai-tts-arabic'  ? 'selected' : '' ?>>PlayAI TTS Arabic</option>
            </select>
          </div>
          <div class="field" id="voiceField">
            <label class="field-label" for="tts_voice">Ses</label>
            <select id="tts_voice" name="tts_voice" class="field-select">
              <?php foreach (TTS_VOICES['playai-tts'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $cur['tts_voice'] === $val ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" id="voiceArField" style="<?= $cur['tts_model'] === 'playai-tts-arabic' ? '' : 'display:none' ?>">
            <label class="field-label" for="tts_voice_ar">Arapça Ses</label>
            <select id="tts_voice_ar" name="tts_voice_ar" class="field-select">
              <?php foreach (TTS_VOICES['playai-tts-arabic'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $cur['tts_voice_ar'] === $val ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>

      <button type="submit" class="save-btn">Kaydet</button>
    </form>
  </main>
</div>

<script>
const TTS_VOICES = <?= json_encode(TTS_VOICES, JSON_UNESCAPED_UNICODE) ?>;

function toggleApiKeyVisibility() {
    const inp = document.getElementById('groq_api_key');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

function showTTSOptions(val) {
    document.getElementById('groqTTSOptions').style.display = val === 'groq' ? '' : 'none';
}

function updateVoices(model) {
    const sel = document.getElementById('tts_voice');
    const voices = TTS_VOICES[model] || TTS_VOICES['playai-tts'];
    sel.innerHTML = '';
    for (const [val, label] of Object.entries(voices)) {
        const opt = document.createElement('option');
        opt.value = val; opt.textContent = label;
        sel.appendChild(opt);
    }
    document.getElementById('voiceArField').style.display =
        model === 'playai-tts-arabic' ? '' : 'none';
}
</script>
</body>
</html>
