'use strict';

// ─── i18n ─────────────────────────────────────────────────────────────────────
const TRANSLATIONS = {
  tr: {
    shareLabelText:  'Bağlantıyı paylaş:',
    myLangLabel:     'Benim dilim',
    otherLangLabel:  'Karşı taraf',
    holdToSpeak:     'Basılı tut',
    recording:       'Dinleniyor…',
    processing:      'İşleniyor…',
    transcribing:    'Yazıya çevriliyor…',
    translating:     'Çevriliyor…',
    emptyText:       'Konuşmaya başlamak için mikrofona basın',
    autoPlayLabel:   'Otomatik seslendir',
    qrNoteText:      'Bu kodu karşı cihazla tarat',
    copiedText:      'Kopyalandı!',
    errorMic:        'Mikrofon erişimi reddedildi',
    errorApi:        'API anahtarı ayarlanmamış',
    errorGeneral:    'Bir hata oluştu',
    you:             'Sen',
    other:           'Karşı taraf',
    langUpdated:     'Dil güncellendi',
  },
  en: {
    shareLabelText:  'Share link:',
    myLangLabel:     'My language',
    otherLangLabel:  'Other side',
    holdToSpeak:     'Hold to speak',
    recording:       'Listening…',
    processing:      'Processing…',
    transcribing:    'Transcribing…',
    translating:     'Translating…',
    emptyText:       'Press the mic button to start speaking',
    autoPlayLabel:   'Auto-play audio',
    qrNoteText:      'Scan this code on the other device',
    copiedText:      'Copied!',
    errorMic:        'Microphone access denied',
    errorApi:        'API key not configured',
    errorGeneral:    'An error occurred',
    you:             'You',
    other:           'Other side',
    langUpdated:     'Language updated',
  },
  de: {
    shareLabelText:  'Link teilen:',
    myLangLabel:     'Meine Sprache',
    otherLangLabel:  'Gegenseite',
    holdToSpeak:     'Halten zum Sprechen',
    recording:       'Hört zu…',
    processing:      'Verarbeite…',
    transcribing:    'Transkribiere…',
    translating:     'Übersetze…',
    emptyText:       'Mikrofon drücken zum Starten',
    autoPlayLabel:   'Auto-Wiedergabe',
    qrNoteText:      'QR-Code scannen',
    copiedText:      'Kopiert!',
    errorMic:        'Mikrofonzugriff verweigert',
    errorApi:        'API-Schlüssel nicht konfiguriert',
    errorGeneral:    'Ein Fehler ist aufgetreten',
    you:             'Du',
    other:           'Gegenseite',
    langUpdated:     'Sprache aktualisiert',
  },
  fr: {
    shareLabelText:  'Partager le lien :',
    myLangLabel:     'Ma langue',
    otherLangLabel:  'Autre côté',
    holdToSpeak:     'Maintenir pour parler',
    recording:       'Écoute…',
    processing:      'Traitement…',
    transcribing:    'Transcription…',
    translating:     'Traduction…',
    emptyText:       'Appuyez sur le micro pour commencer',
    autoPlayLabel:   'Lecture auto',
    qrNoteText:      'Scannez ce code',
    copiedText:      'Copié !',
    errorMic:        'Accès micro refusé',
    errorApi:        'Clé API non configurée',
    errorGeneral:    'Une erreur est survenue',
    you:             'Vous',
    other:           'Autre côté',
    langUpdated:     'Langue mise à jour',
  },
  es: {
    shareLabelText:  'Compartir enlace:',
    myLangLabel:     'Mi idioma',
    otherLangLabel:  'El otro lado',
    holdToSpeak:     'Mantener para hablar',
    recording:       'Escuchando…',
    processing:      'Procesando…',
    transcribing:    'Transcribiendo…',
    translating:     'Traduciendo…',
    emptyText:       'Presiona el micrófono para empezar',
    autoPlayLabel:   'Reproducción auto',
    qrNoteText:      'Escanea este código',
    copiedText:      '¡Copiado!',
    errorMic:        'Acceso al micrófono denegado',
    errorApi:        'Clave API no configurada',
    errorGeneral:    'Ocurrió un error',
    you:             'Tú',
    other:           'El otro lado',
    langUpdated:     'Idioma actualizado',
  },
};

function getLang() {
  const stored = localStorage.getItem('ui_lang');
  if (stored) return stored;
  const nav = (navigator.language || 'en').split('-')[0].toLowerCase();
  return TRANSLATIONS[nav] ? nav : 'en';
}

function t(key) {
  const lang = getLang();
  return (TRANSLATIONS[lang] && TRANSLATIONS[lang][key])
    ? TRANSLATIONS[lang][key]
    : (TRANSLATIONS['en'][key] || key);
}

// ─── State ────────────────────────────────────────────────────────────────────
let myLang        = document.getElementById('myLangSelect')?.value    || 'tr';
let otherLang     = document.getElementById('otherLangSelect')?.value || 'en';
let mediaRecorder = null;
let audioChunks   = [];
let isRecording   = false;
let lastMsgId     = 0;
let pollTimer     = null;
let autoPlay      = true;
let audioUnlocked = false;
let pendingQueue  = [];
let isPlaying     = false;
let pendingTranscript = '';   // transcription waiting for user confirmation
let isConfirming  = false;    // confirm UI visible

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  applyI18n();
  loadAutoPlayPref();
  await loadServerSettings();   // TTS engine localStorage'a yazılmadan polling/play başlamasın
  startPolling();
  loadHistory();

  // Unlock audio on first interaction (any gesture type)
  ['touchstart', 'mousedown', 'click', 'keydown'].forEach(evt =>
    document.addEventListener(evt, unlockAudio, { once: true, passive: true })
  );

  // Wire up confirm/cancel buttons
  document.getElementById('confirmSendBtn')?.addEventListener('click', confirmSend);
  document.getElementById('confirmCancelBtn')?.addEventListener('click', cancelTranscript);
  document.getElementById('cancelRecBtn')?.addEventListener('click', handleCancelRec);
});

function applyI18n() {
  const ids = ['shareLabelText','myLangLabel','otherLangLabel','emptyText','autoPlayLabel','qrNoteText','recordLabel'];
  ids.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = t(id.replace('Label', 'Label').replace('Text','Text'));
  });

  const recordLabel = document.getElementById('recordLabel');
  if (recordLabel) recordLabel.textContent = t('holdToSpeak');

  const emptyText = document.getElementById('emptyText');
  if (emptyText) emptyText.textContent = t('emptyText');

  const shareLbl = document.getElementById('shareLabelText');
  if (shareLbl) shareLbl.textContent = t('shareLabelText');

  const myLbl = document.getElementById('myLangLabel');
  if (myLbl) myLbl.textContent = t('myLangLabel');

  const otherLbl = document.getElementById('otherLangLabel');
  if (otherLbl) otherLbl.textContent = t('otherLangLabel');

  const apLabel = document.getElementById('autoPlayLabel');
  if (apLabel) apLabel.textContent = t('autoPlayLabel');

  const qrNote = document.getElementById('qrNoteText');
  if (qrNote) qrNote.textContent = t('qrNoteText');
}

function loadAutoPlayPref() {
  const pref = localStorage.getItem('autoPlay');
  autoPlay = pref === null ? true : pref === 'true';
  const toggle = document.getElementById('autoPlayToggle');
  if (toggle) toggle.checked = autoPlay;
}

function toggleAutoPlay(val) {
  autoPlay = val;
  localStorage.setItem('autoPlay', String(val));
}

// ─── Safe JSON fetch helper ───────────────────────────────────────────────────
async function fetchJSON(url, options = {}) {
  const res = await fetch(url, options);
  const ct  = res.headers.get('content-type') || '';
  if (!ct.includes('application/json')) {
    const text = await res.text();
    throw new Error(`HTTP ${res.status}: ${text.replace(/<[^>]+>/g, '').trim().slice(0, 120)}`);
  }
  const data = await res.json();
  return data;
}

// ─── History (avoid replaying on load) ───────────────────────────────────────
async function loadHistory() {
  try {
    const data = await fetchJSON(`api/messages.php?session_id=${SESSION_ID}&since=0`);
    if (data.messages && data.messages.length) {
      data.messages.forEach(m => renderMessage(m, false));
      lastMsgId = data.messages[data.messages.length - 1].id;
      scrollToBottom();
    }
  } catch (_) {}
}

// ─── Polling ──────────────────────────────────────────────────────────────────
function startPolling() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(pollMessages, 2000);
}

async function pollMessages() {
  try {
    const data = await fetchJSON(`api/messages.php?session_id=${SESSION_ID}&since=${lastMsgId}`);
    if (!data.messages || !data.messages.length) return;
    data.messages.forEach(m => {
      renderMessage(m, true);
      lastMsgId = Math.max(lastMsgId, m.id);
    });
    scrollToBottom();
  } catch (_) {}
}

// ─── Render messages ──────────────────────────────────────────────────────────
function renderMessage(msg, withTTS) {
  const isOwn = msg.speaker === ROLE;
  hideEmptyState();

  const ttsLang = isOwn ? otherLang : myLang;

  const div = document.createElement('div');
  div.className = `msg ${isOwn ? 'own' : 'other'}`;
  div.dataset.id = msg.id;

  const time = new Date(msg.created_at * 1000)
    .toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  // Build bubble safely — NO inline JS in attributes (escaping hell)
  div.innerHTML = `
    <div class="msg-bubble">
      <div class="msg-original">${escHtml(msg.original_text)}</div>
      <div class="msg-translated">${escHtml(msg.translated_text)}</div>
    </div>
    <div class="msg-footer">
      <span class="msg-time">${time}</span>
      <button class="play-btn" title="Seslendir">🔊</button>
    </div>
  `;

  // Attach handler via closure — text/lang never touch HTML attributes
  div.querySelector('.play-btn').addEventListener('click', (e) => {
    e.stopPropagation();
    unlockAudio();
    playTTS(msg.translated_text, ttsLang);
  });

  document.getElementById('conversation').appendChild(div);

  if (withTTS && autoPlay && !isOwn) {
    queueTTS(msg.translated_text, ttsLang);
  }
}

function hideEmptyState() {
  const es = document.getElementById('emptyState');
  if (es) es.remove();
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(str) {
  return String(str).replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function scrollToBottom() {
  const c = document.getElementById('conversation');
  if (c) c.scrollTop = c.scrollHeight;
}

// ─── Recording ────────────────────────────────────────────────────────────────
function startRec(e) {
  e.preventDefault();
  if (isRecording || isConfirming) return;
  if (!HAS_API_KEY) { setStatus(t('errorApi'), 'error'); return; }
  unlockAudio();
  doStartRec();
}

async function doStartRec() {
  try {
    const stream  = await navigator.mediaDevices.getUserMedia({ audio: true });
    const options = {};
    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
      options.mimeType = 'audio/webm;codecs=opus';
    } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
      options.mimeType = 'audio/mp4';
    }

    mediaRecorder = new MediaRecorder(stream, options);
    audioChunks   = [];
    isRecording   = true;

    mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };
    mediaRecorder.onstop = () => {
      stream.getTracks().forEach(t => t.stop());
      processAudio();
    };

    mediaRecorder.start(250);
    setRecordingUI(true);
    setStatus(t('recording'));
  } catch (err) {
    setStatus(t('errorMic'), 'error');
    console.error(err);
  }
}

function stopRec(e) {
  e.preventDefault();
  if (!isRecording || !mediaRecorder) return;
  isRecording = false;
  if (mediaRecorder.state !== 'inactive') {
    mediaRecorder.stop();
  }
  setRecordingUI(false);
  setStatus(t('processing'));
}

function cancelRec(e) {
  e.preventDefault();
  if (!isRecording || !mediaRecorder) return;
  isRecording = false;
  mediaRecorder.onstop = () => {
    mediaRecorder.stream?.getTracks().forEach(t => t.stop());
  };
  if (mediaRecorder.state !== 'inactive') mediaRecorder.stop();
  setRecordingUI(false);
  setStatus('');
  clearTranscript();
}

function setRecordingUI(on) {
  const btn       = document.getElementById('recordBtn');
  const label     = document.getElementById('recordLabel');
  const icon      = document.getElementById('recordIcon');
  const cancelBtn = document.getElementById('cancelRecBtn');
  if (!btn) return;
  if (on) {
    btn.classList.add('recording');
    if (label)     label.textContent       = t('recording');
    if (icon)      icon.textContent        = '⏹';
    if (cancelBtn) cancelBtn.style.display = 'flex';
  } else {
    btn.classList.remove('recording');
    btn.classList.add('processing');
    if (label)     label.textContent       = t('processing');
    if (icon)      icon.textContent        = '⏳';
    if (cancelBtn) cancelBtn.style.display = 'none';
  }
}

function setProcessingDone() {
  const btn       = document.getElementById('recordBtn');
  const label     = document.getElementById('recordLabel');
  const icon      = document.getElementById('recordIcon');
  const cancelBtn = document.getElementById('cancelRecBtn');
  if (!btn) return;
  btn.classList.remove('processing', 'recording');
  if (label)     label.textContent       = t('holdToSpeak');
  if (icon)      icon.textContent        = '🎤';
  if (cancelBtn) cancelBtn.style.display = 'none';
}

function handleCancelRec(e) {
  if (e) { e.preventDefault(); e.stopPropagation(); }
  if (!isRecording) return;
  isRecording = false;
  if (mediaRecorder) {
    mediaRecorder.onstop = () => {};   // discard
    if (mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    mediaRecorder.stream?.getTracks().forEach(t => t.stop());
  }
  audioChunks = [];
  setRecordingUI(false);
  setProcessingDone();
  setStatus('');
  clearTranscript();
}

// ─── Audio processing ─────────────────────────────────────────────────────────
async function processAudio() {
  if (!audioChunks.length) { setProcessingDone(); setStatus(''); return; }

  const mimeType   = audioChunks[0].type || 'audio/webm';
  let   blob       = new Blob(audioChunks, { type: mimeType });
  audioChunks      = [];

  // Step 1: Transcribe
  setStatus(`<span class="spinner"></span>${t('transcribing')}`);

  // gpt-4o-audio-preview only accepts wav/mp3 — convert when non-verbal is enabled
  const nonVerbal  = localStorage.getItem('stt_nonverbal') === '1';
  let   audioName  = 'audio.' + mimeExt(mimeType);
  let   sendNonVerbal = false; // only set true if WAV conversion actually succeeded

  if (nonVerbal) {
    setStatus(`<span class="spinner"></span>Ses dönüştürülüyor…`);
    try {
      blob        = await blobToWav(blob);
      audioName   = 'audio.wav';
      sendNonVerbal = true;
    } catch (convErr) {
      console.warn('WAV conversion failed, falling back to Whisper:', convErr);
      setStatus('⚠ Dönüştürme başarısız, standart Whisper kullanılıyor');
    }
    setStatus(`<span class="spinner"></span>${t('transcribing')}`);
  }

  const formData = new FormData();
  formData.append('audio',     blob,   audioName);
  formData.append('language',  myLang);
  formData.append('nonverbal', sendNonVerbal ? '1' : '0'); // PHP uses this to pick model

  let transcribed = '';
  try {
    const data = await fetchJSON('api/transcribe.php', { method: 'POST', body: formData });
    if (data.error) throw new Error(data.error);
    transcribed = data.text?.trim() || '';
  } catch (err) {
    setStatus('✗ ' + (err.message || t('errorGeneral')), 'error');
    setProcessingDone();
    return;
  }

  if (!transcribed) { setStatus(''); setProcessingDone(); clearTranscript(); return; }

  // Show transcript and ask for confirmation
  showTranscript(transcribed);
  showConfirmUI(transcribed);
}

// ─── Confirm / cancel transcript ──────────────────────────────────────────────
function showConfirmUI(text) {
  pendingTranscript = text;
  isConfirming      = true;
  const box  = document.getElementById('transcriptBox');
  const btns = document.getElementById('confirmBtns');
  if (box)  { box.removeAttribute('readonly'); box.focus(); box.select(); }
  if (btns)   btns.style.display = 'flex';
  setProcessingDone();
  setStatus('');
}

function hideConfirmUI() {
  isConfirming      = false;
  pendingTranscript = '';
  const box  = document.getElementById('transcriptBox');
  const btns = document.getElementById('confirmBtns');
  if (box)  box.setAttribute('readonly', 'true');
  if (btns) btns.style.display = 'none';
}

async function confirmSend() {
  // Use whatever text is currently in the box (user may have edited it)
  const box  = document.getElementById('transcriptBox');
  const text = (box?.value || pendingTranscript).trim();
  hideConfirmUI();
  if (!text) { clearTranscript(); return; }
  await doTranslate(text);
}

function cancelTranscript() {
  hideConfirmUI();
  clearTranscript();
  setStatus('');
}

async function doTranslate(transcribed) {
  setStatus(`<span class="spinner"></span>${t('translating')}`);
  try {
    const data = await fetchJSON('api/translate.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        text:        transcribed,
        source_lang: myLang,
        target_lang: otherLang,
        session_id:  SESSION_ID,
        speaker:     ROLE,
      }),
    });
    if (data.error) throw new Error(data.error);
  } catch (err) {
    setStatus('✗ ' + (err.message || t('errorGeneral')), 'error');
    setProcessingDone();
    return;
  }

  setStatus('');
  clearTranscript();
  setProcessingDone();
  await pollMessages();
}

function mimeExt(mime) {
  if (mime.includes('mp4') || mime.includes('m4a')) return 'mp4';
  if (mime.includes('ogg'))  return 'ogg';
  if (mime.includes('wav'))  return 'wav';
  if (mime.includes('mpeg')) return 'mp3';
  return 'webm';
}

function showTranscript(text) {
  const box = document.getElementById('transcriptBox');
  if (box) { box.value = text; box.classList.add('has-text'); }
}
function clearTranscript() {
  const box = document.getElementById('transcriptBox');
  if (box) { box.value = ''; box.classList.remove('has-text'); }
}

// ─── Status bar ───────────────────────────────────────────────────────────────
function setStatus(html, type = '') {
  const bar = document.getElementById('statusBar');
  if (!bar) return;
  bar.innerHTML = html;
  bar.style.color = type === 'error' ? 'var(--error)' : '';
  if (html) {
    setTimeout(() => { if (bar.innerHTML === html) bar.innerHTML = ''; }, type === 'error' ? 4000 : 30000);
  }
}

// ─── AudioContext (shared, survives across plays) ─────────────────────────────
let sharedAudioCtx = null;
function getAudioCtx() {
  if (!sharedAudioCtx || sharedAudioCtx.state === 'closed') {
    sharedAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }
  return sharedAudioCtx;
}

// ─── TTS ──────────────────────────────────────────────────────────────────────
function queueTTS(text, lang) {
  pendingQueue.push({ text, lang });
  if (!isPlaying) drainQueue();
}

async function drainQueue() {
  if (!pendingQueue.length) { isPlaying = false; return; }
  isPlaying = true;
  const { text, lang } = pendingQueue.shift();
  await playTTS(text, lang);
  drainQueue();
}

async function loadServerSettings() {
  try {
    const data = await fetchJSON('api/settings.php');
    if (data.tts_engine)    localStorage.setItem('tts_engine',    data.tts_engine);
    if (data.minimax_tts_voice) localStorage.setItem('minimax_tts_voice', data.minimax_tts_voice);
    if (data.stt_nonverbal !== undefined) localStorage.setItem('stt_nonverbal', data.stt_nonverbal);
  } catch (_) {}
}

async function playTTS(text, lang) {
  if (!text) return;
  const engine = localStorage.getItem('tts_engine') || 'webspeech';
  if (engine === 'minimax') {
    await playMiniMaxTTS(text, lang);
  } else {
    // Web Speech API
    if (!window.speechSynthesis) {
      setStatus('🔇 Tarayıcı TTS desteklemiyor', 'error');
      return;
    }
    await playWebSpeech(text, lang);
  }
}

function playWebSpeech(text, lang) {
  return new Promise(resolve => {
    if (!window.speechSynthesis) { resolve(); return; }
    window.speechSynthesis.cancel();
    const utt  = new SpeechSynthesisUtterance(text);
    utt.lang   = langBCP47(lang);
    utt.rate   = 1.0;
    utt.pitch  = 1.0;
    utt.onend  = resolve;
    utt.onerror= resolve;
    window.speechSynthesis.speak(utt);
  });
}

function langBCP47(code) {
  const map = { tr:'tr-TR', en:'en-US', de:'de-DE', fr:'fr-FR', es:'es-ES',
                it:'it-IT', pt:'pt-PT', ru:'ru-RU', ar:'ar-SA', zh:'zh-CN',
                ja:'ja-JP', ko:'ko-KR' };
  return map[code] || code;
}

async function playMiniMaxTTS(text, lang) {
  // --- 1. Fetch audio from PHP ---
  let arrayBuffer;
  try {
    const res = await fetch('api/tts.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text, lang }),
    });
    if (!res.ok) {
      const ct = res.headers.get('content-type') || '';
      let msg = 'HTTP ' + res.status;
      if (ct.includes('json')) {
        try { const e = await res.json(); msg = e.error || msg; } catch (_) {}
      }
      setStatus('🔇 TTS: ' + msg, 'error');
      return;
    }
    arrayBuffer = await res.arrayBuffer();
    if (!arrayBuffer.byteLength) { setStatus('🔇 TTS: boş yanıt', 'error'); return; }
  } catch (fetchErr) {
    setStatus('🔇 TTS bağlantı hatası', 'error');
    return;
  }

  // --- 2. Web Audio API (best for auto-play: gesture-independent after resume) ---
  const ctx = getAudioCtx();
  let webAudioOk = false;
  if (ctx) {
    try {
      await ctx.resume();
      const audioBuffer = await ctx.decodeAudioData(arrayBuffer.slice(0));
      await new Promise((resolve, reject) => {
        const source   = ctx.createBufferSource();
        source.buffer  = audioBuffer;
        source.connect(ctx.destination);
        source.onended = resolve;
        source.onerror = reject;
        source.start(0);
      });
      webAudioOk = true;
    } catch (webAudioErr) {
      console.warn('Web Audio failed, trying HTML Audio:', webAudioErr.message || webAudioErr);
    }
  }
  if (webAudioOk) return;

  // --- 3. HTML Audio fallback (works for direct user clicks) ---
  try {
    const blob = new Blob([arrayBuffer], { type: 'audio/mpeg' });
    const url  = URL.createObjectURL(blob);
    await new Promise((resolve) => {
      const audio   = new Audio(url);
      audio.oncanplaythrough = () => {
        audio.play().catch(playErr => {
          console.warn('audio.play() blocked:', playErr.message);
          setStatus('🔇 Ses oynatılamadı: tarayıcı izni gerekli', 'error');
          URL.revokeObjectURL(url);
          resolve();
        });
      };
      audio.onended = () => { URL.revokeObjectURL(url); resolve(); };
      audio.onerror = (e) => {
        console.error('Audio load error:', e);
        URL.revokeObjectURL(url);
        resolve();
      };
      audio.src = url;
      audio.load();
    });
  } catch (err) {
    console.error('TTS HTML Audio:', err);
  }
}

// ─── Audio unlock ──────────────────────────────────────────────────────────────
function unlockAudio() {
  if (audioUnlocked) return;
  audioUnlocked = true;
  // Resume shared AudioContext during user gesture → subsequent auto-plays work
  try { getAudioCtx().resume(); } catch (_) {}
  // Warm up Web Speech API
  try {
    if (window.speechSynthesis) {
      window.speechSynthesis.cancel();
      window.speechSynthesis.speak(new SpeechSynthesisUtterance(''));
    }
  } catch (_) {}
}

// ─── Share / QR ───────────────────────────────────────────────────────────────
async function copyLink() {
  try {
    await navigator.clipboard.writeText(SHARE_URL);
    const btn = document.getElementById('copyBtn');
    if (btn) { btn.textContent = '✓'; setTimeout(() => btn.textContent = '📋', 2000); }
    setStatus(t('copiedText'));
  } catch (_) {
    prompt('Bağlantıyı kopyala:', SHARE_URL);
  }
}

function toggleQR() {
  const panel = document.getElementById('qrPanel');
  if (panel) panel.classList.toggle('hidden');
}

// ─── Language change ──────────────────────────────────────────────────────────
async function changeMyLang(val) {
  myLang = val;
  localStorage.setItem('ui_lang', val.substring(0, 2));
  await patchSession({ lang: val, role: ROLE });
}

async function changeOtherLang(val) {
  otherLang = val;
  const otherRole = ROLE === 'host' ? 'guest' : 'host';
  await patchSession({ lang: val, role: otherRole });
}

async function patchSession(payload) {
  try {
    await fetchJSON('api/session.php', {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: SESSION_ID, ...payload }),
    });
  } catch (_) {}
}

// ─── WAV encoder (browser-side, no server deps) ───────────────────────────────
// gpt-4o-audio-preview only accepts wav/mp3 so we convert here before upload.
async function blobToWav(blob) {
  const TARGET_RATE = 16000; // 16 kHz mono – optimal for speech recognition

  const arrayBuffer = await blob.arrayBuffer();

  // Use a fresh AudioContext for decoding (suspended state is fine for decodeAudioData)
  const decodeCtx = new (window.AudioContext || window.webkitAudioContext)();
  let audioBuffer;
  try {
    // Callback form for maximum browser compat (iOS Safari older versions)
    audioBuffer = await new Promise((resolve, reject) => {
      decodeCtx.decodeAudioData(arrayBuffer, resolve, reject);
    });
  } finally {
    try { decodeCtx.close(); } catch (_) {}
  }

  if (!audioBuffer || audioBuffer.duration <= 0) {
    throw new Error('Ses tamponu boş');
  }

  // Resample to TARGET_RATE via OfflineAudioContext
  const numSamples = Math.max(1, Math.ceil(audioBuffer.duration * TARGET_RATE));
  const offline    = new OfflineAudioContext(1, numSamples, TARGET_RATE);
  const source     = offline.createBufferSource();
  source.buffer    = audioBuffer;
  source.connect(offline.destination);
  source.start(0);
  const resampled  = await offline.startRendering();

  return audioBufferToWavBlob(resampled);
}

function audioBufferToWavBlob(buffer) {
  const numCh      = 1;
  const sampleRate = buffer.sampleRate;
  const bitDepth   = 16;
  const samples    = buffer.getChannelData(0);
  const dataLen    = samples.length * 2;
  const ab         = new ArrayBuffer(44 + dataLen);
  const view       = new DataView(ab);

  const str = (off, s) => { for (let i = 0; i < s.length; i++) view.setUint8(off + i, s.charCodeAt(i)); };
  str(0, 'RIFF');
  view.setUint32(4,  36 + dataLen,                   true);
  str(8, 'WAVE');
  str(12, 'fmt ');
  view.setUint32(16, 16,                              true);  // PCM chunk size
  view.setUint16(20, 1,                               true);  // PCM format
  view.setUint16(22, numCh,                           true);
  view.setUint32(24, sampleRate,                      true);
  view.setUint32(28, sampleRate * numCh * bitDepth / 8, true);
  view.setUint16(32, numCh * bitDepth / 8,            true);
  view.setUint16(34, bitDepth,                        true);
  str(36, 'data');
  view.setUint32(40, dataLen,                         true);

  let off = 44;
  for (let i = 0; i < samples.length; i++, off += 2) {
    view.setInt16(off, Math.max(-1, Math.min(1, samples[i])) * 0x7FFF, true);
  }
  return new Blob([ab], { type: 'audio/wav' });
}
