"use strict";

/* ============================================
   Профили пользователей в текстах (комментарии, ответы, лента):
   - ник автора -> ссылка /users/{id};
   - @упоминание -> ссылка /users/{id}, если логин уже известен странице.

   Бэк не трогаем: отдельного API «логин -> id» нет, поэтому карта
   строится только из данных, которые страница уже получила:
     * атрибуты текущего пользователя (data-me-id / data-me-username,
       data-cu-id / data-cu-name);
     * creator.id + creator.username у комментариев/ответов (include=creator);
     * готовые ссылки /users/{id} с текстом-логином (карточки, профиль).
   Неизвестный логин остаётся обычным текстом — он не ведёт в никуда.
   Если бэкенд позже добавит резолв по логину, сюда достаточно
   вернуть один fetch в resolve(), остальной код не меняется.

   Используется из book.js (детали книги/статьи) и card-feed.js (лента).
   ============================================ */
window.LoreUsers = (function () {
  var byName = Object.create(null);   // username в нижнем регистре -> id
  var pending = [];                   // ещё не ссылки: [{node, username}]
  var flushTimer = 0;
  var PENDING_LIMIT = 500;

  function key(username) {
    return String(username || '').toLowerCase();
  }

  /** Запомнить связь «логин -> id» (приходит из include=creator). */
  function remember(id, username) {
    var uid = Number(id) || 0;
    var k = key(username);
    if (!uid || !k) return 0;
    if (!byName[k]) {
      byName[k] = uid;
      scheduleFlush(); // раньше отрисованные @упоминания станут ссылками
    }
    return byName[k];
  }

  function idOf(username) {
    return byName[key(username)] || 0;
  }

  function profileUrl(id) {
    return '/users/' + Number(id);
  }

  /** Безопасно: только textNode/ссылки, без innerHTML. */
  function link(cls, href, text) {
    var a = document.createElement('a');
    a.className = cls;
    if (href) a.href = href;
    a.textContent = text;
    return a;
  }

  /**
   * Ник автора -> ссылка на профиль. Если id неизвестен (или ещё не
   * пришёл) — обычный текст: ссылка без профиля хуже, чем её отсутствие.
   */
  function renderAuthor(el, label, id, username) {
    if (!el) return;
    remember(id, username);
    var uid = idOf(username) || Number(id) || 0;
    var text = label || username || '';

    if (uid > 0) el.replaceChildren(link('user-link', profileUrl(uid), text));
    else el.textContent = text;
  }

  /** Дождаться конца кадра и превратить известные упоминания в ссылки. */
  function scheduleFlush() {
    if (flushTimer) return;
    flushTimer = setTimeout(flush, 0);
  }

  function flush() {
    flushTimer = 0;
    if (!pending.length) return;

    var rest = [];
    for (var i = 0; i < pending.length; i++) {
      var p = pending[i];
      var uid = idOf(p.username);

      if (!uid) { rest.push(p); continue; }        // логин так и не известен
      if (!p.node.isConnected) continue;           // вёрстку перерисовали

      var a = link('user-mention', profileUrl(uid), p.node.nodeValue);
      a.dataset.mention = p.username;
      p.node.replaceWith(a);
    }
    pending = rest;
  }

  /**
   * @упоминание -> ссылка, если логин уже встречался на странице;
   * иначе — обычный текст (позже может стать ссылкой, когда автор
   * появится в другом комментарии).
   */
  function mentionNode(full, username) {
    var known = idOf(username);
    if (known) {
      var a = link('user-mention', profileUrl(known), full);
      a.dataset.mention = username;
      return a;
    }

    var text = document.createTextNode(full);
    if (pending.length < PENDING_LIMIT) pending.push({ node: text, username: key(username) });
    return text;
  }

  /**
   * Текст комментария: обычный текст + @упоминания ссылками.
   * email (user@example.com) упоминанием не считаем.
   */
  function renderText(el, text) {
    if (!el) return;
    var value = String(text == null ? '' : text);
    var re = /@([A-Za-z0-9_]{3,30})/g;
    var out = document.createDocumentFragment();
    var last = 0;
    var m;

    while ((m = re.exec(value)) !== null) {
      var start = m.index;
      var prev = start > 0 ? value.charAt(start - 1) : '';
      if (prev !== '' && /[A-Za-z0-9_.@-]/.test(prev)) continue; // домен в email и т.п.

      if (start > last) out.appendChild(document.createTextNode(value.slice(last, start)));
      out.appendChild(mentionNode('@' + m[1], m[1]));
      last = re.lastIndex;
    }
    if (last < value.length) out.appendChild(document.createTextNode(value.slice(last)));

    el.replaceChildren(out);
    scheduleFlush(); // уже отрисованные упоминания могли стать известными
  }

  /** "@username " — начало ответа (задание: при ответе добавляется @ник). */
  function mentionPrefix(username) {
    return username ? '@' + username + ' ' : '';
  }

  /**
   * Гарантирует, что текст ответа начинается с @ника адресата.
   * "@adm" не срабатывает на "@admin" — смотрим границу слова.
   */
  function withMention(text, username) {
    var value = String(text == null ? '' : text).trim();
    if (!username) return value;
    var mention = '@' + username;
    var re = new RegExp('^' + mention.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![A-Za-z0-9_])');
    if (re.test(value)) return value;
    return mention + ' ' + value;
  }

  /** Собрать «логин -> id» из уже отрисованной страницы. */
  function harvest(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var i;
    var el;
    var d;

    var tagged = scope.querySelectorAll('[data-author-id], [data-me-id], [data-cu-id]');
    for (i = 0; i < tagged.length; i++) {
      d = tagged[i].dataset;
      remember(d.authorId || d.meId || d.cuId, d.authorUsername || d.meUsername || d.cuName);
    }

    // Готовые ссылки на профиль: id в href, логин — в тексте.
    var links = scope.querySelectorAll('a[href^="/users/"]');
    for (i = 0; i < links.length; i++) {
      el = links[i];
      var id = Number(String(el.getAttribute('href') || '').replace(/^\/users\/?/, ''));
      var label = (el.textContent || '').trim();
      if (id > 0 && /^[A-Za-z0-9_]{3,30}$/.test(label)) remember(id, label);
    }

    return Object.keys(byName).length;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { harvest(document); });
  } else {
    harvest(document);
  }

  return {
    remember: remember,
    idOf: idOf,
    profileUrl: profileUrl,
    harvest: harvest,
    renderAuthor: renderAuthor,
    renderText: renderText,
    mentionPrefix: mentionPrefix,
    withMention: withMention
  };
})();
