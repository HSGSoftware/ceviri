const languages = [
  { code: 'en', label: 'English' },
  { code: 'tr', label: 'Turkish' },
  { code: 'es', label: 'Spanish' },
  { code: 'fr', label: 'French' },
  { code: 'de', label: 'German' },
  { code: 'it', label: 'Italian' },
  { code: 'pt', label: 'Portuguese' },
  { code: 'ar', label: 'Arabic' },
  { code: 'ja', label: 'Japanese' },
  { code: 'ko', label: 'Korean' },
  { code: 'ru', label: 'Russian' },
  { code: 'zh-CN', label: 'Chinese (Simplified)' }
];

const fallbackTranslations = new Map([
  ['hello::tr', 'Merhaba'],
  ['how are you?::tr', 'Nasılsın?'],
  ['good morning::tr', 'Günaydın'],
  ['thank you::tr', 'Teşekkür ederim'],
  ['merhaba::en', 'Hello'],
  ['nasılsın?::en', 'How are you?'],
  ['günaydın::en', 'Good morning'],
  ['teşekkür ederim::en', 'Thank you']
]);

const historyStorageKey = 'ceviri-history';
const sourceLanguage = document.getElementById('sourceLanguage');
const targetLanguage = document.getElementById('targetLanguage');
const sourceText = document.getElementById('sourceText');
const translatedText = document.getElementById('translatedText');
const sourceMeta = document.getElementById('sourceMeta');
const targetMeta = document.getElementById('targetMeta');
const statusMessage = document.getElementById('statusMessage');
const historyList = document.getElementById('historyList');
const historyItemTemplate = document.getElementById('historyItemTemplate');

function populateLanguageSelect(select, defaultCode) {
  languages.forEach((language) => {
    const option = document.createElement('option');
    option.value = language.code;
    option.textContent = language.label;
    if (language.code === defaultCode) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}

function updateSourceMeta() {
  sourceMeta.textContent = `${sourceText.value.length} characters`;
}

function setStatus(message, isError = false) {
  statusMessage.textContent = message;
  statusMessage.style.color = isError ? '#b42318' : 'var(--muted)';
}

function speakText(text, lang) {
  if (!('speechSynthesis' in window) || !text.trim()) {
    setStatus('Speech playback is not available in this browser.', true);
    return;
  }

  window.speechSynthesis.cancel();
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = lang;
  window.speechSynthesis.speak(utterance);
}

function loadHistory() {
  try {
    return JSON.parse(localStorage.getItem(historyStorageKey)) ?? [];
  } catch {
    return [];
  }
}

function saveHistory(historyItems) {
  localStorage.setItem(historyStorageKey, JSON.stringify(historyItems.slice(0, 6)));
}

function renderHistory() {
  const historyItems = loadHistory();
  historyList.innerHTML = '';

  if (!historyItems.length) {
    const empty = document.createElement('li');
    empty.textContent = 'No translations saved yet.';
    empty.className = 'history-item';
    historyList.appendChild(empty);
    return;
  }

  historyItems.forEach((item) => {
    const fragment = historyItemTemplate.content.cloneNode(true);
    const button = fragment.querySelector('.history-entry');
    fragment.querySelector('.history-languages').textContent = `${item.fromLabel} → ${item.toLabel}`;
    fragment.querySelector('.history-source').textContent = item.source;
    fragment.querySelector('.history-translation').textContent = item.translation;

    button.addEventListener('click', () => {
      sourceLanguage.value = item.from;
      targetLanguage.value = item.to;
      sourceText.value = item.source;
      translatedText.value = item.translation;
      updateSourceMeta();
      targetMeta.textContent = 'Loaded from history';
      setStatus('Loaded a recent translation.');
    });

    historyList.appendChild(fragment);
  });
}

function pushHistory(entry) {
  const historyItems = loadHistory().filter(
    (item) => !(item.source === entry.source && item.from === entry.from && item.to === entry.to)
  );
  historyItems.unshift(entry);
  saveHistory(historyItems);
  renderHistory();
}

async function translateText() {
  const text = sourceText.value.trim();
  const from = sourceLanguage.value;
  const to = targetLanguage.value;

  if (!text) {
    setStatus('Please enter text to translate.', true);
    sourceText.focus();
    return;
  }

  if (from === to) {
    translatedText.value = text;
    targetMeta.textContent = 'Same language selected';
    setStatus('Source and target languages match, so the text was copied directly.');
    return;
  }

  setStatus('Translating...');
  targetMeta.textContent = 'Fetching translation';

  try {
    const query = new URLSearchParams({ q: text, langpair: `${from}|${to}` });
    const response = await fetch(`https://api.mymemory.translated.net/get?${query.toString()}`);

    if (!response.ok) {
      throw new Error(`Translation request failed with ${response.status}`);
    }

    const payload = await response.json();
    const translation = payload.responseData?.translatedText?.trim();

    if (!translation) {
      throw new Error('No translation returned');
    }

    translatedText.value = translation;
    targetMeta.textContent = 'Translated online';
    setStatus('Translation complete.');
    pushHistory({
      source: text,
      translation,
      from,
      to,
      fromLabel: sourceLanguage.selectedOptions[0].textContent,
      toLabel: targetLanguage.selectedOptions[0].textContent
    });
  } catch (error) {
    const fallbackKey = `${text.toLowerCase()}::${to}`;
    const fallbackValue = fallbackTranslations.get(fallbackKey);

    if (fallbackValue) {
      translatedText.value = fallbackValue;
      targetMeta.textContent = 'Offline fallback';
      setStatus('Live translation was unavailable, so a built-in fallback phrase was used.');
      pushHistory({
        source: text,
        translation: fallbackValue,
        from,
        to,
        fromLabel: sourceLanguage.selectedOptions[0].textContent,
        toLabel: targetLanguage.selectedOptions[0].textContent
      });
      return;
    }

    translatedText.value = '';
    targetMeta.textContent = 'Unavailable';
    setStatus(`Translation failed: ${error.message}`, true);
  }
}

async function pasteFromClipboard() {
  if (!navigator.clipboard?.readText) {
    setStatus('Clipboard paste is not available in this browser.', true);
    return;
  }

  try {
    sourceText.value = await navigator.clipboard.readText();
    updateSourceMeta();
    setStatus('Pasted text from your clipboard.');
  } catch {
    setStatus('Clipboard access was denied.', true);
  }
}

async function copyTranslation() {
  if (!translatedText.value.trim()) {
    setStatus('Translate something first to copy the result.', true);
    return;
  }

  if (!navigator.clipboard?.writeText) {
    setStatus('Clipboard copy is not available in this browser.', true);
    return;
  }

  await navigator.clipboard.writeText(translatedText.value);
  setStatus('Translation copied to your clipboard.');
}

function downloadTranslation() {
  if (!translatedText.value.trim()) {
    setStatus('Nothing to download yet.', true);
    return;
  }

  const blob = new Blob([translatedText.value], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = 'translation.txt';
  anchor.click();
  URL.revokeObjectURL(url);
  setStatus('Translation downloaded as a text file.');
}

document.getElementById('translateButton').addEventListener('click', translateText);
document.getElementById('swapLanguages').addEventListener('click', () => {
  [sourceLanguage.value, targetLanguage.value] = [targetLanguage.value, sourceLanguage.value];
  [sourceText.value, translatedText.value] = [translatedText.value, sourceText.value];
  updateSourceMeta();
  targetMeta.textContent = translatedText.value ? 'Swapped languages' : 'Ready';
  setStatus('Languages swapped.');
});
document.getElementById('clearSource').addEventListener('click', () => {
  sourceText.value = '';
  translatedText.value = '';
  updateSourceMeta();
  targetMeta.textContent = 'Ready';
  setStatus('Cleared the translator.');
});
document.getElementById('pasteSource').addEventListener('click', pasteFromClipboard);
document.getElementById('copyTranslation').addEventListener('click', copyTranslation);
document.getElementById('downloadTranslation').addEventListener('click', downloadTranslation);
document.getElementById('speakSource').addEventListener('click', () => speakText(sourceText.value, sourceLanguage.value));
document.getElementById('speakTranslation').addEventListener('click', () => speakText(translatedText.value, targetLanguage.value));
document.getElementById('clearHistory').addEventListener('click', () => {
  localStorage.removeItem(historyStorageKey);
  renderHistory();
  setStatus('Translation history cleared.');
});
sourceText.addEventListener('input', updateSourceMeta);
sourceText.addEventListener('keydown', (event) => {
  if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
    translateText();
  }
});

populateLanguageSelect(sourceLanguage, 'en');
populateLanguageSelect(targetLanguage, 'tr');
updateSourceMeta();
renderHistory();
