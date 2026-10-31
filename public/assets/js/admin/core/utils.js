/**
 * Мелкие чистые хелперы: экранирование HTML, язык, цвета.
 */

export function esc(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function currentLang() {
  return String(window.LORE_LOCALE || document.documentElement.lang || 'en');
}

/** Название языка по коду: Intl.DisplayNames (без хардкода en/ru). */
export function langName(code, fallback) {
  var value = String(code || '');
  if (!value) return '';
  try {
    var names = new Intl.DisplayNames([LoreI18n ? LoreI18n.locale : currentLang()], { type: 'language' });
    var named = names.of(value);
    if (named) return named;
  } catch (_) {}
  return fallback || value.toUpperCase();
}

export function colorForApi(value) {
  return String(value || '').replace(/^#/, '');
}

export function normalizeColor(value) {
  var c = String(value || '');
  return /^[0-9a-f]{6}$/i.test(c) ? '#' + c : c;
}
