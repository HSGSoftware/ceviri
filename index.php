<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Redirect if no session
$sessionId = $_GET['session'] ?? '';
$role      = $_GET['role']    ?? 'host';
if (!in_array($role, ['host', 'guest'], true)) $role = 'host';

if (!$sessionId) {
    $sessionId = generateId();
    header("Location: index.php?session={$sessionId}&role=host");
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = :id");
$stmt->bindValue(':id', $sessionId, SQLITE3_TEXT);
$session = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$session) {
    // Auto-detect browser language
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    $browserLang = strtolower(substr($acceptLang, 0, 2));
    $supported = array_keys(LANGUAGES);
    $hostLang  = in_array($browserLang, $supported) ? $browserLang : 'tr';
    $guestLang = ($hostLang === 'tr') ? 'en' : 'tr';

    $ins = $db->prepare("INSERT INTO sessions (id, host_lang, guest_lang) VALUES (:id,:hl,:gl)");
    $ins->bindValue(':id', $sessionId, SQLITE3_TEXT);
    $ins->bindValue(':hl', $hostLang,  SQLITE3_TEXT);
    $ins->bindValue(':gl', $guestLang, SQLITE3_TEXT);
    $ins->execute();
    $session = ['id' => $sessionId, 'host_lang' => $hostLang, 'guest_lang' => $guestLang];
}

$baseURL  = getBaseURL();
$shareURL = $baseURL . "/index.php?session={$sessionId}&role=guest";
$hasApiKey = !empty(getSetting('groq_api_key'));

$myLang    = ($role === 'host') ? $session['host_lang']  : $session['guest_lang'];
$otherLang = ($role === 'host') ? $session['guest_lang'] : $session['host_lang'];
$langsJson = json_encode(LANGUAGES, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($myLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d0d18">
<title>LocalTalk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div id="app">
  <!-- Header -->
  <header class="header">
    <div class="header-left">
      <span class="logo">🌐 LocalTalk</span>
      <?php if (!$hasApiKey): ?>
      <a href="settings.php" class="api-warning">⚠ API Anahtarı Gerekli</a>
      <?php endif; ?>
    </div>
    <a href="settings.php" class="icon-btn" title="Ayarlar">⚙</a>
  </header>

  <!-- Share bar -->
  <div class="share-bar">
    <div class="share-info">
      <span class="share-label" id="shareLabelText">Bağlantıyı paylaş:</span>
      <span class="share-url" id="shareUrlDisplay"><?= htmlspecialchars($shareURL) ?></span>
    </div>
    <div class="share-actions">
      <button class="share-btn" id="copyBtn" onclick="copyLink()">📋</button>
      <button class="share-btn" id="qrBtn" onclick="toggleQR()">QR</button>
    </div>
  </div>
  <div id="qrPanel" class="qr-panel hidden">
    <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?= urlencode($shareURL) ?>&size=180x180&bgcolor=1c1c2e&color=a78bfa&margin=10"
         alt="QR" class="qr-img" onerror="this.parentElement.innerHTML='<p style=color:#888>QR için internet bağlantısı gerekli</p>'">
    <p class="qr-note" id="qrNoteText">Bu kodu karşı cihazla tarat</p>
  </div>

  <!-- Language selector -->
  <div class="lang-bar">
    <div class="lang-side">
      <label class="lang-label" id="myLangLabel">Benim dilem</label>
      <select id="myLangSelect" class="lang-select" onchange="changeMyLang(this.value)">
        <?php foreach (LANGUAGES as $code => $name): ?>
        <option value="<?= $code ?>" <?= $myLang === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="lang-arrow">⇄</div>
    <div class="lang-side">
      <label class="lang-label" id="otherLangLabel">Karşı taraf</label>
      <select id="otherLangSelect" class="lang-select" onchange="changeOtherLang(this.value)">
        <?php foreach (LANGUAGES as $code => $name): ?>
        <option value="<?= $code ?>" <?= $otherLang === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Conversation -->
  <main class="conversation" id="conversation">
    <div class="empty-state" id="emptyState">
      <div class="empty-icon">🎤</div>
      <p id="emptyText">Konuşmaya başlamak için mikrofon butonuna basın</p>
    </div>
  </main>

  <!-- Status bar -->
  <div class="status-bar" id="statusBar"></div>

  <!-- Controls -->
  <div class="controls">

    <!-- Transcript + editable review area -->
    <div id="transcriptWrap" class="transcript-wrap">
      <textarea id="transcriptBox" class="transcript-box" rows="2" readonly
        placeholder="Konuşmanız burada görünecek…"></textarea>
      <!-- Confirm / cancel after transcription -->
      <div id="confirmBtns" class="confirm-btns" style="display:none">
        <button class="confirm-btn confirm-cancel-btn" id="confirmCancelBtn">✕ İptal</button>
        <button class="confirm-btn confirm-send-btn"   id="confirmSendBtn">✓ Gönder</button>
      </div>
    </div>

    <!-- Record row: cancel (X) + mic button -->
    <div class="record-row">
      <button class="cancel-rec-btn" id="cancelRecBtn" style="display:none">✕</button>
      <button
        class="record-btn"
        id="recordBtn"
        ontouchstart="startRec(event)"
        ontouchend="stopRec(event)"
        ontouchcancel="handleCancelRec(event)"
        onmousedown="startRec(event)"
        onmouseup="stopRec(event)"
        onmouseleave="stopRec(event)"
      >
        <span class="record-icon" id="recordIcon">🎤</span>
        <span class="record-label" id="recordLabel">Basılı tut</span>
      </button>
    </div>

    <div class="tts-toggle">
      <label>
        <input type="checkbox" id="autoPlayToggle" checked onchange="toggleAutoPlay(this.checked)">
        <span id="autoPlayLabel">Otomatik seslendir</span>
      </label>
    </div>
  </div>
</div>

<script>
const SESSION_ID  = <?= json_encode($sessionId) ?>;
const ROLE        = <?= json_encode($role) ?>;
const SHARE_URL   = <?= json_encode($shareURL) ?>;
const LANGUAGES   = <?= $langsJson ?>;
const HAS_API_KEY = <?= $hasApiKey ? 'true' : 'false' ?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>
