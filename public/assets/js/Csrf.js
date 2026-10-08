/**
 * csrf.js — общий хелпер CSRF для фронта.
 *
 * Бэк сравнивает куку csrf_token с полем _token или заголовком X-CSRF-Token.
 * Кука не HttpOnly, поэтому токен читаем из неё; запасной вариант —
 * скрытое поле _token из csrfField().
 *
 * Подключить ПЕРВЫМ скриптом в layouts/main.php (и auth.php):
 *   <script src="/assets/js/csrf.js"></script>
 *
 * Имя глобала LoreCsrf выбрано намеренно: card-feed.js объявляет свою
 * глобальную функцию csrfToken(), и window.csrfToken затёрлось бы ею.
 */
(function () {
  'use strict';

  var COOKIE = 'csrf_token';
  var HEADER = 'X-CSRF-Token';
  var SAFE   = ['GET', 'HEAD', 'OPTIONS'];

  function token() {
    var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + COOKIE + '=([^;]*)'));
    if (m && m[1]) {
      try { return decodeURIComponent(m[1]); } catch (_) { return m[1]; }
    }
    var el = document.querySelector('input[type="hidden"][name="_token"]');
    return el ? el.value : '';
  }

  /** fetch, который сам добавляет заголовок токена к небезопасным методам. */
  function csrfFetch(url, options) {
    options = options || {};
    var method = (options.method || 'GET').toUpperCase();

    if (SAFE.indexOf(method) === -1) {
      var headers = new Headers(options.headers || {});
      if (!headers.has(HEADER)) headers.set(HEADER, token());
      options.headers = headers;
    }
    if (!options.credentials) options.credentials = 'same-origin';

    return fetch(url, options);
  }

  window.LoreCsrf = { token: token, fetch: csrfFetch };
})();