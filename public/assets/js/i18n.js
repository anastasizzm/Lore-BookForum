"use strict";

/* ============================================
   i18n для JS + автоподстановка ?lang=<locale> в /api/*.

   Словарь (window.LORE_I18N) и локаль (window.LORE_LOCALE) выводит
   partial src/partials/i18n.php. Подключается ПЕРВЫМ скриптом в <head>.

   Использование:
     LoreI18n.t('sort.label', { value: 'Newest' })
     LoreI18n.t('js.network_error')
     LoreI18n.t('js.password_min', { min: 8 })

   Пути точечные — как в PHP Translator, но БЕЗ префикса 'common.'
   (потому что LORE_I18N — это уже содержимое common.json).

   Бэк определяет локаль по ?lang=, поэтому window.fetch патчится:
   ко всем URL, содержащим '/api/', добавляется ?lang=<locale>.
   ============================================ */
window.LoreI18n = (function () {
  var dict = window.LORE_I18N || {};
  var locale = String(window.LORE_LOCALE || document.documentElement.lang || 'en');

  function lookup(key) {
    if (Object.prototype.hasOwnProperty.call(dict, key)) return dict[key];
    var parts = String(key).split('.');
    var cur = dict;
    for (var i = 0; i < parts.length; i++) {
      if (cur == null || typeof cur !== 'object') return undefined;
      cur = cur[parts[i]];
    }
    return cur;
  }

  function has(key) {
    return typeof lookup(key) === 'string';
  }

  function t(key, params) {
    var value = lookup(key);
    if (typeof value !== 'string') {
      console.warn('[i18n] missing key: ' + key);
      return key;
    }
    var text = value;
    if (params) {
      Object.keys(params).sort(function (a, b) { return b.length - a.length; }).forEach(function (name) {
        text = text.split(':' + name).join(String(params[name]));
      });
    }
    return text;
  }

  /** Как t(), но без warn — при отсутствии ключа вернёт fallback. */
  function tOr(key, fallback, params) {
    return has(key) ? t(key, params) : (fallback != null ? fallback : key);
  }

  /** Enum-значение: enumValue('genre', 'fantasy') → t('enums.genre.fantasy'). */
  function enumValue(group, id) {
    if (id == null || id === '') return '';
    var key = 'enums.' + group + '.' + id;
    return has(key) ? t(key) : String(id);
  }

  /** Добавляет ?lang=<locale> к URL (сохраняя существующие параметры). */
  function withLang(url) {
    try {
      var u = new URL(url, location.origin);
      u.searchParams.set('lang', locale);
      return u.pathname + u.search + u.hash;
    } catch (_) {
      return url;
    }
  }

  var LOCALE_DATE = {
    ru: { day: '2-digit', month: '2-digit', year: 'numeric' },
    en: { month: 'short', day: 'numeric', year: 'numeric' }
  };

  /** Даты из API: {date: "..."} или строка. Формат — по локали. */
  function formatDate(value) {
    var raw = (value && typeof value === 'object' && !(value instanceof Date))
      ? value.date : value;
    if (raw instanceof Date) raw = raw.toISOString();
    var m = /(\d{4})-(\d{2})-(\d{2})/.exec(String(raw || ''));
    if (!m) return '';

    var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    var opts = LOCALE_DATE[locale] || LOCALE_DATE.ru;
    try {
      return new Intl.DateTimeFormat(locale, opts).format(d);
    } catch (_) {
      return m[3] + '.' + m[2] + '.' + m[1];
    }
  }

  /* ---------- Автоподстановка ?lang= во все /api/* ----------
     Бэк определяет локаль по ?lang=, а API-запросов много и в разных файлах.
     Патчим window.fetch один раз — все существующие вызовы fetch('/api/...')
     автоматически получают ?lang=<текущая локаль>. Ничего в остальных JS
     менять не нужно. */
  (function patchFetch() {
    if (!window.fetch || window.__loreFetchPatched) return;
    window.__loreFetchPatched = true;

    var originalFetch = window.fetch.bind(window);

    window.fetch = function (input, init) {
      var url;

      if (typeof input === 'string') {
        url = input;
      } else if (input instanceof URL) {
        url = input.toString();
      } else if (input && input.url) {
        url = input.url;  // Request
      }

      // Только /api/* на своём origin — не трогаем внешние и статику.
      if (url && url.indexOf('/api/') !== -1 && url.charAt(0) === '/') {
        try {
          var u = new URL(url, location.origin);
          u.searchParams.set('lang', locale);
          var patched = u.pathname + u.search + u.hash;

          if (typeof input === 'string') {
            input = patched;
          } else if (input instanceof URL) {
            input = new URL(patched, location.origin);
          } else if (input && input.url) {
            input = new Request(patched, input);
          }
        } catch (_) { /* не URL — оставляем как есть */ }
      }

      return originalFetch(input, init);
    };
  })();

  return {
    t: t,
    tOr: tOr,
    has: has,
    locale: locale,
    enumValue: enumValue,
    withLang: withLang,
    formatDate: formatDate
  };
})();