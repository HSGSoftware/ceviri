(function () {
  const cfg = window.__APP__ || {};
  const I = cfg.i18n || {};

  function apiAbs(path) {
    if (!path || /^https?:\/\//i.test(path)) {
      return path;
    }
    const o = window.location.origin;
    return path.startsWith('/') ? o + path : new URL(path, window.location.href).href;
  }

  const el = (id) => document.getElementById(id);
  const yourLang = el('your-lang');
  const theirLang = el('their-lang');
  const btnStart = el('btn-start');
  const btnRec = el('btn-record');
  const statusEl = el('status');
  const yourText = el('your-text');
  const theirText = el('their-text');
  const copyBtn = el('copy-url');

  let stream = null;
  let mediaRecorder = null;
  let chunks = [];
  let armed = false;

  function setStatus(msg, cls) {
    statusEl.textContent = msg || '';
    statusEl.className = 'status' + (cls ? ' ' + cls : '');
  }

  function t(key) {
    return I[key] || key;
  }

  function bcp47(code) {
    const m = {
      zh: 'zh-CN',
      pt: 'pt-BR',
      no: 'nb-NO',
    };
    return m[code] || code;
  }

  function pickMime() {
    const c = [
      'audio/webm;codecs=opus',
      'audio/webm',
      'audio/mp4',
    ];
    for (const x of c) {
      if (MediaRecorder.isTypeSupported(x)) return x;
    }
    return '';
  }

  function loadVoices(cb) {
    const run = () => {
      if (speechSynthesis.getVoices().length) cb();
    };
    run();
    speechSynthesis.onvoiceschanged = run;
  }

  function speakTranslated(text, langCode) {
    const lang = bcp47(langCode);
    speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = lang;
    u.rate = 1;
    const voices = speechSynthesis.getVoices();
    const want = lang.toLowerCase();
    let best = voices.find((v) => v.lang && v.lang.toLowerCase().startsWith(want));
    if (!best) {
      const short = want.split('-')[0];
      best = voices.find((v) => v.lang && v.lang.toLowerCase().startsWith(short));
    }
    if (best) u.voice = best;
    speechSynthesis.speak(u);
  }

  async function startMic() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      armed = true;
      btnStart.disabled = true;
      btnRec.disabled = false;
      setStatus('');
      loadVoices(() => {});
    } catch {
      setStatus(t('error_mic'), 'err');
    }
  }

  function startRecording() {
    if (!armed || !stream) return;
    chunks = [];
    const mime = pickMime();
    try {
      mediaRecorder = mime
        ? new MediaRecorder(stream, { mimeType: mime })
        : new MediaRecorder(stream);
    } catch {
      mediaRecorder = new MediaRecorder(stream);
    }
    mediaRecorder.ondataavailable = (e) => {
      if (e.data && e.data.size > 0) chunks.push(e.data);
    };
    const sliceMs = 250;
    try {
      mediaRecorder.start(sliceMs);
    } catch {
      mediaRecorder.start();
    }
    btnRec.classList.add('recording');
    setStatus(t('listening'), 'busy');
  }

  async function stopRecording() {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') {
      btnRec.classList.remove('recording');
      return;
    }
    const mr = mediaRecorder;
    mediaRecorder = null;

    if (typeof mr.requestData === 'function') {
      try {
        mr.requestData();
      } catch (_) {}
    }
    await new Promise((resolve) => {
      mr.onstop = resolve;
      mr.stop();
    });
    btnRec.classList.remove('recording');
    setStatus(t('processing'), 'busy');

    const blob = new Blob(chunks, { type: mr.mimeType || 'audio/webm' });
    if (blob.size < 200) {
      setStatus('');
      return;
    }

    const fd = new FormData();
    fd.append('audio', blob, 'clip.webm');
    fd.append('language', yourLang.value);

    async function readJsonResponse(r) {
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch {
        return null;
      }
    }

    let r1;
    try {
      r1 = await fetch(apiAbs(cfg.apiTranscribe), { method: 'POST', body: fd });
    } catch {
      setStatus(t('error_network'), 'err');
      return;
    }
    const tr = await readJsonResponse(r1);
    if (!r1.ok || !tr || typeof tr !== 'object') {
      setStatus(t('error_api'), 'err');
      return;
    }

    const spoken = (tr.text || '').trim();
    if (!spoken) {
      setStatus('');
      return;
    }

    yourText.textContent = spoken;
    yourText.classList.remove('empty');

    const from = yourLang.value;
    const to = theirLang.value;
    let out = spoken;
    if (from !== to) {
      let r2;
      try {
        r2 = await fetch(apiAbs(cfg.apiTranslate), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ text: spoken, from, to }),
        });
      } catch {
        setStatus(t('error_network'), 'err');
        return;
      }
      const tj = await readJsonResponse(r2);
      if (!r2.ok || !tj || typeof tj !== 'object') {
        setStatus(t('error_api'), 'err');
        return;
      }
      out = (tj.text || '').trim() || spoken;
    }

    theirText.textContent = out;
    theirText.classList.remove('empty');
    setStatus('');
    speakTranslated(out, to);
  }

  function bindHold(elBtn) {
    const down = (e) => {
      e.preventDefault();
      startRecording();
    };
    const up = (e) => {
      e.preventDefault();
      stopRecording();
    };
    elBtn.addEventListener('mousedown', down);
    elBtn.addEventListener('mouseup', up);
    elBtn.addEventListener('mouseleave', up);
    elBtn.addEventListener('touchstart', down, { passive: false });
    elBtn.addEventListener('touchend', up);
    elBtn.addEventListener('touchcancel', up);
  }

  btnStart.addEventListener('click', () => startMic());
  bindHold(btnRec);

  if (copyBtn && cfg.shareUrl) {
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(cfg.shareUrl);
        copyBtn.textContent = t('copied');
        setTimeout(() => {
          copyBtn.textContent = t('copy');
        }, 2000);
      } catch {
        const ta = document.createElement('textarea');
        ta.value = cfg.shareUrl;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        copyBtn.textContent = t('copied');
        setTimeout(() => {
          copyBtn.textContent = t('copy');
        }, 2000);
      }
    });
  }
})();
