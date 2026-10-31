/**
 * Список доступных языков: GET /api/additional/languages (с фолбэком).
 */
import { t } from '../../core/i18n.js';
import { renderLangMenu, setStatus } from './form.js';
import { dict } from './state.js';

var FALLBACK_LANGUAGES = [
  { code: 'en', name: 'English' },
  { code: 'ru', name: 'Русский' },
  { code: 'es', name: 'Español' }
];

export function loadLanguages() {
  if (dict.languages) return Promise.resolve(dict.languages);
  if (dict.languagesLoading) return dict.languagesLoading;

  setStatus(t('admin.dict_lang_loading'));

  dict.languagesLoading = fetch('/api/additional/languages?page=1&ps=50', {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  }).then(function (res) {
    return res.text().then(function (raw) {
      if (!res.ok || /^\s*</.test(raw)) throw new Error('HTTP ' + res.status);
      var data = raw ? JSON.parse(raw) : null;
      var list = (data && Array.isArray(data.items)) ? data.items
               : (Array.isArray(data) ? data : []);
      var languages = list.map(function (entry) {
        return {
          code: String((entry && (entry.code || entry.langCode || entry.value)) || ''),
          name: entry && entry.name ? String(entry.name) : ''
        };
      }).filter(function (entry) { return entry.code !== ''; });

      if (!languages.length) throw new Error('empty languages list');
      return languages;
    });
  }).then(function (languages) {
    dict.languages = languages;
    return languages;
  }, function (err) {
    console.warn('[admin] GET /api/additional/languages failed, using fallback', err);
    dict.languages = FALLBACK_LANGUAGES.slice();
    return dict.languages;
  }).then(function (languages) {
    dict.languagesLoading = null;
    setStatus('');
    renderLangMenu();
    return languages;
  });

  return dict.languagesLoading;
}
