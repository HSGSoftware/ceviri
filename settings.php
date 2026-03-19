<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$saved   = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appBaseUrl     = trim($_POST['app_base_url']          ?? '');
    $openaiApiKey   = trim($_POST['openai_api_key']        ?? '');
    $apiKey         = trim($_POST['groq_api_key']          ?? '');
    $minimaxApiKey  = trim($_POST['minimax_api_key']       ?? '');
    $sttModel       = $_POST['stt_model']                  ?? 'whisper-large-v3-turbo';
    $transModel     = $_POST['translation_model']          ?? 'llama-3.3-70b-versatile';
    $ttsEngine      = $_POST['tts_engine']                 ?? 'webspeech';
    $minimaxModel   = $_POST['minimax_tts_model']          ?? 'speech-02-turbo';
    $minimaxVoice   = trim($_POST['minimax_tts_voice']     ?? 'Turkish_Trustworthyman');
    $minimaxSpeed   = $_POST['minimax_tts_speed']          ?? '1.0';
    $minimaxPitch   = $_POST['minimax_tts_pitch']          ?? '0';
    $minimaxVol     = $_POST['minimax_tts_vol']            ?? '1.0';

    // Normalize base URL
    if ($appBaseUrl && !preg_match('#^https?://#', $appBaseUrl)) {
        $appBaseUrl = 'https://' . $appBaseUrl;
    }
    $appBaseUrl = rtrim($appBaseUrl, '/');

    if (!array_key_exists($sttModel,     STT_MODELS))     $errors[] = 'Geçersiz STT modeli';
    if (!array_key_exists($minimaxModel, MINIMAX_MODELS)) $errors[] = 'Geçersiz MiniMax modeli';
    if (!in_array($ttsEngine, ['webspeech', 'minimax'], true)) $errors[] = 'Geçersiz TTS motoru';
    // transModel: herhangi bir string kabul — yeni modeller listede olmayabilir

    if (empty($errors)) {
        setSetting('app_base_url',        $appBaseUrl);
        setSetting('openai_api_key',      $openaiApiKey);
        setSetting('groq_api_key',        $apiKey);
        setSetting('minimax_api_key',     $minimaxApiKey);
        setSetting('stt_model',           $sttModel);
        setSetting('translation_model',   $transModel);
        setSetting('tts_engine',          $ttsEngine);
        setSetting('minimax_tts_model',   $minimaxModel);
        setSetting('minimax_tts_voice',   $minimaxVoice ?: 'Turkish_Trustworthyman');
        setSetting('minimax_tts_speed',   $minimaxSpeed);
        setSetting('minimax_tts_pitch',   $minimaxPitch);
        setSetting('minimax_tts_vol',     $minimaxVol);
        $saved = true;
    }
}

$cur = [
    'app_base_url'       => getSetting('app_base_url'),
    'openai_api_key'     => getSetting('openai_api_key'),
    'groq_api_key'       => getSetting('groq_api_key'),
    'minimax_api_key'    => getSetting('minimax_api_key'),
    'stt_model'          => getSetting('stt_model',          'whisper-1'),
    'translation_model'  => getSetting('translation_model',  'llama-3.3-70b-versatile'),
    'tts_engine'         => getSetting('tts_engine',         'webspeech'),
    'minimax_tts_model'  => getSetting('minimax_tts_model',  'speech-02-turbo'),
    'minimax_tts_voice'  => getSetting('minimax_tts_voice',  'Turkish_Trustworthyman'),
    'minimax_tts_speed'  => getSetting('minimax_tts_speed',  '1.0'),
    'minimax_tts_pitch'  => getSetting('minimax_tts_pitch',  '0'),
    'minimax_tts_vol'    => getSetting('minimax_tts_vol',    '1.0'),
];

$effectiveBaseURL = getBaseURL();

$localIP = getLocalIP();
$port        = $_SERVER['SERVER_PORT'] ?? 80;
$presetsJson = json_encode(MINIMAX_VOICE_PRESETS, JSON_UNESCAPED_UNICODE);
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
<style>
.slider-wrap { display:flex; align-items:center; gap:10px; }
.slider-wrap input[type=range] { flex:1; accent-color:var(--primary); }
.slider-val { min-width:36px; text-align:right; font-size:14px; font-family:monospace; color:var(--text); }
.voice-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.voice-chip {
  padding:8px 10px; border:1px solid var(--border); border-radius:var(--radius-sm);
  font-size:12px; cursor:pointer; background:var(--bg); color:var(--text-muted);
  text-align:center; transition:all .15s; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.voice-chip:hover { border-color:var(--primary); color:var(--text); }
.voice-chip.active { border-color:var(--primary); background:rgba(124,58,237,.2); color:var(--primary); }
.test-row { display:flex; gap:8px; align-items:center; }
.test-btn {
  padding:8px 14px; background:var(--surface2); border:1px solid var(--border);
  border-radius:var(--radius-sm); color:var(--text); cursor:pointer; font-size:13px;
  transition:background .15s; white-space:nowrap; flex-shrink:0;
}
.test-btn:hover { background:var(--border); }
.test-btn:disabled { opacity:.5; cursor:wait; }
.load-voices-btn {
  padding:6px 12px; background:none; border:1px solid var(--primary);
  border-radius:var(--radius-sm); color:var(--primary); cursor:pointer; font-size:12px;
  transition:all .15s;
}
.load-voices-btn:hover { background:rgba(124,58,237,.1); }
.voice-filter { margin-bottom:8px; }
.voice-filter select {
  background:var(--bg); border:1px solid var(--border); color:var(--text);
  padding:6px 10px; border-radius:var(--radius-sm); font-size:13px; font-family:inherit;
}
#voiceLoadStatus { font-size:12px; color:var(--text-muted); margin-top:4px; display:none; }
.url-status { display:flex; align-items:center; gap:8px; padding:10px 12px;
  border-radius:var(--radius-sm); font-size:13px; margin-top:6px; }
.url-status.active  { background:rgba(34,197,94,.1);  border:1px solid rgba(34,197,94,.3);  color:var(--success); }
.url-status.default { background:rgba(148,163,184,.08); border:1px solid var(--border); color:var(--text-muted); }
.cf-steps { list-style:none; display:flex; flex-direction:column; gap:8px; margin-top:8px; }
.cf-steps li { display:flex; gap:10px; font-size:13px; line-height:1.5; }
.cf-steps li .step-num { 
  width:22px; height:22px; border-radius:50%; background:var(--primary); 
  color:#fff; display:flex; align-items:center; justify-content:center;
  font-size:11px; font-weight:600; flex-shrink:0; margin-top:1px;
}
.cf-steps code { 
  background:var(--surface2); padding:2px 6px; border-radius:4px; 
  font-size:12px; font-family:monospace; color:var(--accent);
}
</style>
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

    <form method="POST" class="settings-form" id="settingsForm">

      <!-- ── Cloudflare / Bağlantı ──────────────────────────────────────── -->
      <section class="settings-section">
        <h2 class="section-title">🌐 Bağlantı & Paylaşım URL'i</h2>

        <div class="field">
          <label class="field-label" for="app_base_url">Site URL <small style="color:var(--text-muted);font-weight:400">(Cloudflare Tunnel, ngrok vb.)</small></label>
          <input type="url" id="app_base_url" name="app_base_url"
            class="field-input"
            value="<?= htmlspecialchars($cur['app_base_url']) ?>"
            placeholder="https://xxxx.trycloudflare.com"
            autocomplete="off"
            oninput="updateUrlPreview(this.value)">
          <p class="field-hint">Boş bırakılırsa yerel IP kullanılır. Cloudflare Tunnel URL'ini buraya girin.</p>

          <?php if ($cur['app_base_url']): ?>
          <div class="url-status active">
            ✓ Aktif: <strong><?= htmlspecialchars($cur['app_base_url']) ?></strong>
          </div>
          <?php else: ?>
          <div class="url-status default">
            Şu an: <span><?= htmlspecialchars($effectiveBaseURL) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Cloudflare Tunnel kurulum rehberi (Termux) -->
        <div class="field">
          <label class="field-label">Cloudflare Tunnel kurulumu (Termux)</label>
          <ul class="cf-steps">
            <li>
              <span class="step-num">1</span>
              <span>Termux'ta cloudflared yükle:<br>
                <code>pkg install cloudflared</code>
              </span>
            </li>
            <li>
              <span class="step-num">2</span>
              <span>PHP sunucusunu başlat (ayrı sekme):<br>
                <code>php -S 0.0.0.0:8080 -t /path/to/app</code>
              </span>
            </li>
            <li>
              <span class="step-num">3</span>
              <span>Tunnel başlat (ayrı sekme):<br>
                <code>cloudflared tunnel --url http://localhost:8080</code>
              </span>
            </li>
            <li>
              <span class="step-num">4</span>
              <span>Terminalde çıkan <code>https://xxxx.trycloudflare.com</code> URL'ini yukarıdaki alana yapıştır ve kaydet.</span>
            </li>
            <li>
              <span class="step-num">5</span>
              <span>Sayfayı HTTPS URL üzerinden aç → mikrofon izni çalışır ✓</span>
            </li>
          </ul>
        </div>

        <!-- Ağ bilgisi -->
        <div class="info-row" style="margin-top:4px">
          <span class="info-label">Yerel IP</span>
          <span class="info-value mono"><?= htmlspecialchars($localIP) ?>:<?= htmlspecialchars((string)$port) ?></span>
        </div>
      </section>

      <!-- OpenAI API (STT) -->
      <section class="settings-section">
        <h2 class="section-title">OpenAI API — Ses Tanıma (Whisper)</h2>
        <div class="field">
          <label class="field-label" for="openai_api_key">OpenAI API Anahtarı</label>
          <div class="input-wrap">
            <input type="password" id="openai_api_key" name="openai_api_key"
              class="field-input" value="<?= htmlspecialchars($cur['openai_api_key']) ?>"
              placeholder="sk-..." autocomplete="off">
            <button type="button" class="eye-btn" onclick="toggleVis('openai_api_key')">👁</button>
          </div>
          <p class="field-hint"><a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a></p>
        </div>
        <div class="field">
          <label class="field-label" for="stt_model">Whisper Modeli</label>
          <select id="stt_model" name="stt_model" class="field-select">
            <?php foreach (STT_MODELS as $val => $label): ?>
            <option value="<?= $val ?>" <?= $cur['stt_model'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </section>

      <!-- Groq API (Translation) -->
      <section class="settings-section">
        <h2 class="section-title">Groq API — Çeviri (LLM)</h2>
        <div class="field">
          <label class="field-label" for="groq_api_key">Groq API Anahtarı</label>
          <div class="input-wrap">
            <input type="password" id="groq_api_key" name="groq_api_key"
              class="field-input" value="<?= htmlspecialchars($cur['groq_api_key']) ?>"
              placeholder="gsk_..." autocomplete="off">
            <button type="button" class="eye-btn" onclick="toggleVis('groq_api_key')">👁</button>
          </div>
          <p class="field-hint"><a href="https://console.groq.com/keys" target="_blank">console.groq.com/keys</a></p>
        </div>
        <div class="field">
          <label class="field-label" for="translation_model">Çeviri Modeli</label>
          <input type="text" id="translation_model" name="translation_model"
            class="field-input" list="translation_model_list"
            value="<?= htmlspecialchars($cur['translation_model']) ?>"
            placeholder="llama-3.3-70b-versatile">
          <datalist id="translation_model_list">
            <?php foreach (TRANSLATION_MODELS as $val => $label): ?>
            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </datalist>
          <p class="field-hint">Listede olmayan model ID'lerini de yazabilirsiniz.</p>
        </div>
      </section>

      <!-- TTS Engine -->
      <section class="settings-section">
        <h2 class="section-title">Seslendirme (TTS) Motoru</h2>
        <div class="field">
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" name="tts_engine" value="webspeech"
                <?= $cur['tts_engine'] === 'webspeech' ? 'checked' : '' ?>
                onchange="onEngineChange(this.value)">
              Web Speech API <small style="color:var(--text-muted)">(Tarayıcı yerleşik – ücretsiz)</small>
            </label>
            <label class="radio-label">
              <input type="radio" name="tts_engine" value="minimax"
                <?= $cur['tts_engine'] === 'minimax' ? 'checked' : '' ?>
                onchange="onEngineChange(this.value)">
              MiniMax TTS <small style="color:var(--text-muted)">(Yüksek kalite – 40 dil)</small>
            </label>
          </div>
        </div>
      </section>

      <!-- MiniMax TTS settings -->
      <section class="settings-section" id="minimaxSection"
        style="<?= $cur['tts_engine'] !== 'minimax' ? 'display:none' : '' ?>">
        <h2 class="section-title">MiniMax TTS Ayarları</h2>

        <!-- API Key -->
        <div class="field">
          <label class="field-label" for="minimax_api_key">MiniMax API Anahtarı</label>
          <div class="input-wrap">
            <input type="password" id="minimax_api_key" name="minimax_api_key"
              class="field-input" value="<?= htmlspecialchars($cur['minimax_api_key']) ?>"
              placeholder="eyJ..." autocomplete="off">
            <button type="button" class="eye-btn" onclick="toggleVis('minimax_api_key')">👁</button>
          </div>
          <p class="field-hint"><a href="https://platform.minimax.io" target="_blank">platform.minimax.io</a> → API Keys</p>
        </div>

        <!-- Model -->
        <div class="field">
          <label class="field-label" for="minimax_tts_model">Model</label>
          <select id="minimax_tts_model" name="minimax_tts_model" class="field-select">
            <?php foreach (MINIMAX_MODELS as $val => $label): ?>
            <option value="<?= $val ?>" <?= $cur['minimax_tts_model'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Voice ID -->
        <div class="field">
          <label class="field-label">Ses (Voice ID)</label>

          <!-- Language filter for presets -->
          <div class="voice-filter">
            <select id="voiceLangFilter" onchange="showPresets(this.value)">
              <option value="">— Dile göre ön ayarlı sesler —</option>
              <option value="tr">Türkçe</option>
              <option value="en">English</option>
              <option value="de">Deutsch</option>
              <option value="fr">Français</option>
              <option value="es">Español</option>
              <option value="it">Italiano</option>
              <option value="pt">Português</option>
              <option value="ru">Русский</option>
              <option value="ar">العربية</option>
              <option value="zh">中文</option>
              <option value="ja">日本語</option>
              <option value="ko">한국어</option>
            </select>
          </div>

          <!-- Preset chips -->
          <div id="voiceChips" class="voice-grid" style="display:none;margin-bottom:8px"></div>

          <!-- Custom / manual voice ID -->
          <div class="test-row">
            <input type="text" id="minimax_tts_voice" name="minimax_tts_voice"
              class="field-input" style="flex:1"
              value="<?= htmlspecialchars($cur['minimax_tts_voice']) ?>"
              placeholder="Turkish_Trustworthyman">
            <button type="button" class="test-btn" id="testTTSBtn" onclick="testTTS()">▶ Test</button>
          </div>

          <p id="voiceLoadStatus"></p>

          <!-- Load from API -->
          <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <button type="button" class="load-voices-btn" onclick="loadVoicesFromAPI()">
              🔄 API'den sesleri yükle
            </button>
            <span style="font-size:12px;color:var(--text-muted)">API anahtarınızı önce kaydedin</span>
          </div>
          <div id="apiVoiceList" style="display:none;margin-top:10px">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">API'den yüklendi:</div>
            <div id="apiVoiceChips" class="voice-grid"></div>
          </div>
        </div>

        <!-- Speed -->
        <div class="field">
          <label class="field-label">Hız <span class="slider-val" id="speedVal"><?= htmlspecialchars($cur['minimax_tts_speed']) ?></span></label>
          <div class="slider-wrap">
            <span style="font-size:12px;color:var(--text-muted)">0.5</span>
            <input type="range" name="minimax_tts_speed" id="speedSlider"
              min="0.5" max="2.0" step="0.1"
              value="<?= htmlspecialchars($cur['minimax_tts_speed']) ?>"
              oninput="document.getElementById('speedVal').textContent=this.value">
            <span style="font-size:12px;color:var(--text-muted)">2.0</span>
          </div>
        </div>

        <!-- Pitch -->
        <div class="field">
          <label class="field-label">Perde <span class="slider-val" id="pitchVal"><?= htmlspecialchars($cur['minimax_tts_pitch']) ?></span></label>
          <div class="slider-wrap">
            <span style="font-size:12px;color:var(--text-muted)">-12</span>
            <input type="range" name="minimax_tts_pitch" id="pitchSlider"
              min="-12" max="12" step="1"
              value="<?= htmlspecialchars($cur['minimax_tts_pitch']) ?>"
              oninput="document.getElementById('pitchVal').textContent=this.value">
            <span style="font-size:12px;color:var(--text-muted)">+12</span>
          </div>
        </div>

        <!-- Volume -->
        <div class="field">
          <label class="field-label">Ses Seviyesi <span class="slider-val" id="volVal"><?= htmlspecialchars($cur['minimax_tts_vol']) ?></span></label>
          <div class="slider-wrap">
            <span style="font-size:12px;color:var(--text-muted)">0.1</span>
            <input type="range" name="minimax_tts_vol" id="volSlider"
              min="0.1" max="3.0" step="0.1"
              value="<?= htmlspecialchars($cur['minimax_tts_vol']) ?>"
              oninput="document.getElementById('volVal').textContent=this.value">
            <span style="font-size:12px;color:var(--text-muted)">3.0</span>
          </div>
        </div>

        <p class="field-hint" style="margin-top:4px">
          Tüm ses listesi için:
          <a href="https://platform.minimax.io/docs/api-reference/voice-management-get" target="_blank">MiniMax Ses Rehberi</a>
        </p>
      </section>

      <button type="submit" class="save-btn">Kaydet</button>
    </form>
  </main>
</div>

<script>
const VOICE_PRESETS = <?= $presetsJson ?>;

function toggleVis(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

function onEngineChange(val) {
  document.getElementById('minimaxSection').style.display = val === 'minimax' ? '' : 'none';
}

function updateUrlPreview(val) {
  const hint = document.querySelector('.url-status');
  if (!hint) return;
  const trimmed = val.trim().replace(/\/+$/, '');
  if (trimmed) {
    hint.className = 'url-status active';
    hint.innerHTML = '✓ Kaydedince aktif: <strong>' + trimmed + '</strong>';
  } else {
    hint.className = 'url-status default';
    hint.innerHTML = 'Boş — yerel IP kullanılır';
  }
}

function showPresets(lang) {
  const container = document.getElementById('voiceChips');
  const voices = VOICE_PRESETS[lang] || {};
  const keys = Object.keys(voices);
  if (!lang || !keys.length) { container.style.display = 'none'; return; }

  container.innerHTML = '';
  container.style.display = 'grid';
  const currentVoice = document.getElementById('minimax_tts_voice').value;

  keys.forEach(vid => {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'voice-chip' + (vid === currentVoice ? ' active' : '');
    chip.title = vid;
    chip.textContent = voices[vid];
    chip.onclick = () => selectVoice(vid);
    container.appendChild(chip);
  });
}

function selectVoice(vid) {
  document.getElementById('minimax_tts_voice').value = vid;
  document.querySelectorAll('.voice-chip').forEach(c => {
    c.classList.toggle('active', c.title === vid);
  });
}

async function loadVoicesFromAPI() {
  const status = document.getElementById('voiceLoadStatus');
  const panel  = document.getElementById('apiVoiceList');
  const chips  = document.getElementById('apiVoiceChips');
  status.style.display = 'block';
  status.textContent = 'Yükleniyor…';

  try {
    const res  = await fetch('api/voices.php');
    const data = await res.json();
    if (data.error) throw new Error(data.error);

    const voices = data.voices || [];
    status.textContent = `${voices.length} ses yüklendi`;
    chips.innerHTML = '';

    const currentVoice = document.getElementById('minimax_tts_voice').value;
    voices.forEach(v => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'voice-chip' + (v.voice_id === currentVoice ? ' active' : '');
      chip.title = v.voice_id;
      chip.textContent = v.name || v.voice_id;
      chip.onclick = () => selectVoice(v.voice_id);
      chips.appendChild(chip);
    });
    panel.style.display = 'block';
  } catch (err) {
    status.textContent = '✗ ' + err.message;
  }
}

async function testTTS() {
  const btn   = document.getElementById('testTTSBtn');
  const voice = document.getElementById('minimax_tts_voice').value;
  const model = document.getElementById('minimax_tts_model').value;
  const speed = document.getElementById('speedSlider').value;
  const pitch = document.getElementById('pitchSlider').value;
  const vol   = document.getElementById('volSlider').value;

  if (!voice) { alert('Önce bir ses seçin'); return; }

  btn.disabled = true;
  btn.textContent = '⏳';

  // Temporarily save settings via hidden form post
  // Instead, call the TTS endpoint directly with test text
  try {
    // We need to save first to make settings take effect
    // So we submit form data silently and then call tts
    const formData = new FormData(document.getElementById('settingsForm'));
    await fetch('settings.php', { method: 'POST', body: formData });

    const res = await fetch('api/tts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: 'Merhaba, bu bir ses testidir.', lang: 'tr' }),
    });

    if (!res.ok) {
      const ct = res.headers.get('content-type') || '';
      if (ct.includes('json')) {
        const err = await res.json();
        throw new Error(err.error || 'TTS hatası');
      }
      throw new Error('HTTP ' + res.status);
    }

    const blob    = await res.blob();
    const url     = URL.createObjectURL(blob);
    const audio   = new Audio(url);
    audio.onended = () => URL.revokeObjectURL(url);
    await audio.play();
  } catch (err) {
    alert('TTS Hatası: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = '▶ Test';
  }
}
</script>
</body>
</html>
