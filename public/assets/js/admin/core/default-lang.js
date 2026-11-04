/**
 * Язык системы по умолчанию: GET /api/additional/languages/fallback.
 * На нём обязательно заполняется название при создании записей (справочники, роли).
 *
 * Форма ответа бэка не зафиксирована, поэтому код языка ищется в разных видах:
 * "en" | { code | langCode | lang | locale | value | fallback | default } | { data | item | language: … }.
 * Если запрос не удался — временно используем 'en' (без кеширования, при следующем
 * открытии формы запрос повторится).
 */
import { httpRequest } from './http.js';

var FALLBACK = 'en';
var ENDPOINT = '/api/additional/languages/fallback';
var CODE_KEYS = ['code', 'langCode', 'lang_code', 'lang', 'locale', 'value', 'fallback', 'default', 'defaultLang', 'language'];
var WRAP_KEYS = ['data', 'item', 'language', 'result', 'fallback', 'default'];

var cached = null;
var pending = null;

function codeFrom(node, depth) {
  if (typeof node === 'string') {
    var s = node.trim();
    return /^[A-Za-z]{2,3}([-_][A-Za-z0-9]+)?$/.test(s) ? s : '';
  }
  if (!node || typeof node !== 'object' || Array.isArray(node) || depth > 3) return '';
  var keys = CODE_KEYS.concat(WRAP_KEYS);
  for (var i = 0; i < keys.length; i++) {
    var v = node[keys[i]];
    if (v === undefined || v === null) continue;
    var found = codeFrom(v, depth + 1);
    if (found) return found;
  }
  return '';
}

/** Код языка по умолчанию (синхронно): из кеша или 'en', пока ответ не получен. */
export function defaultLang() {
  return cached || FALLBACK;
}

/** Ответ бэка уже получен. */
export function hasDefaultLang() {
  return cached !== null;
}

/** Загружает язык по умолчанию. Всегда резолвится строкой (никогда не reject). */
export function loadDefaultLang() {
  if (cached) return Promise.resolve(cached);
  if (pending) return pending;

  pending = httpRequest('GET', ENDPOINT).then(function (r) {
    var code = '';
    if (r.ok && !/^\s*</.test(r.raw || '')) {
      code = codeFrom(r.data !== null ? r.data : (r.raw || ''), 0);
    }
    if (code) {
      cached = code;
    } else {
      console.warn('[admin] GET ' + ENDPOINT + ': could not read the default language (HTTP ' + r.status +
        '), using "' + FALLBACK + '"', r.data || r.raw);
    }
    pending = null;
    return cached || FALLBACK;
  });
  return pending;
}