document.addEventListener('DOMContentLoaded', () => {
  // ===== Дропдауны =====
  /* 3. Дропдауны: открытие, автозакрытие соседей, закрытие по клику вне и Escape */
document.querySelectorAll('[data-dropdown]').forEach(dd => {
  const trigger = dd.querySelector('[data-dropdown-trigger]');
  if (!trigger) return;

  trigger.addEventListener('click', e => {
    e.stopPropagation();                       // не даём клику дойти до document
    const willOpen = !dd.classList.contains('is-open');

    // ← КЛЮЧЕВОЕ: закрываем все остальные открытые дропдауны
    document.querySelectorAll('[data-dropdown].is-open').forEach(other => {
      if (other !== dd) other.classList.remove('is-open');
    });

    dd.classList.toggle('is-open', willOpen);
  });

  // Клик по пункту меню — закрываем дропдаун перед переходом
  dd.querySelectorAll('.dropdown__item').forEach(item => {
    item.addEventListener('click', () => {
      dd.classList.remove('is-open');
    });
  });
});

// Клик в любом месте вне дропдауна — закрыть все
document.addEventListener('click', () => {
  document.querySelectorAll('[data-dropdown].is-open')
    .forEach(dd => dd.classList.remove('is-open'));
});

// Escape — закрыть все
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('[data-dropdown].is-open')
      .forEach(dd => dd.classList.remove('is-open'));
  }
});

  // ===== Бургер-меню (делегирование событий) =====
  const body = document.body;
  const overlay = document.querySelector('.sidebar-overlay');
  const sidebar = document.querySelector('[data-sidebar]');

  function closeMenu() {
    body.classList.remove('is-menu-open');
  }

  function openMenu() {
    body.classList.add('is-menu-open');
  }

  document.addEventListener('click', function (e) {
    const burgerTarget = e.target.closest('.burger, [data-burger]');
    if (burgerTarget) {
      e.preventDefault();
      e.stopPropagation();
      openMenu();
    }
  });

  // Закрытие по клику на оверлей
  if (overlay) {
    overlay.addEventListener('click', closeMenu);
  }

  // Закрытие при клике на ссылки в сайдбаре
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
  }

  // Закрытие по Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeMenu();
    }
  });

  // ===== Раскрытие сайдбара на десктопе =====
  if (sidebar) {
    const desktop = window.matchMedia('(min-width: 769px)');

    const setExpanded = (value) => {
      sidebar.classList.toggle('is-expanded', value);
      body.classList.toggle('is-sidebar-expanded', value);
    };

    // Клик по пустой области сайдбара
    sidebar.addEventListener('click', (e) => {
      if (!desktop.matches) return;
      if (e.target.closest('a, button, .settings-menu')) return;
      setExpanded(!sidebar.classList.contains('is-expanded'));
    });

    // Клик вне сайдбара
    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target)) setExpanded(false);
    });

    // Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setExpanded(false);
    });

    // Клик по ссылке на десктопе тоже сворачивает
    sidebar.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => setExpanded(false));
    });

    // Переход на мобильную ширину сбрасывает состояние
    desktop.addEventListener('change', (e) => {
      if (!e.matches) setExpanded(false);
    });
  }

  /* ===== Settings menu (sidebar) ===== */
  (function () {
    var toggle = document.querySelector('[data-settings-toggle]');
    var menu = document.querySelector('[data-settings-menu]');

    if (!toggle || !menu) return;

    function closeSettings() {
      menu.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (menu.hidden) {
        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
      } else {
        closeSettings();
      }
    });

    document.addEventListener('click', function (e) {
      if (menu.hidden) return;
      if (!menu.contains(e.target) && !toggle.contains(e.target)) {
        closeSettings();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !menu.hidden) closeSettings();
    });
  })();
});

/* ===== Save book: POST/DELETE /api/books/{id}/save =====
   Кнопка: [data-save-book] с data-book-id; состояние — класс is-active.
   Работает и на странице книги, и на карточках (делегирование).
   Успех: любой 2xx. Ошибка: откат состояния + сообщение. */
(function () {
  var busy = new WeakSet();

  function csrfInput() {
    return document.querySelector(
      '[data-csrf] input[type="hidden"], ' +
      'input[type="hidden"][name*="csrf" i], ' +
      'input[type="hidden"][name*="token" i]'
    );
  }

  function toast(text) {
    var el = document.createElement('div');
    el.setAttribute('role', 'alert');
    el.textContent = text;
    el.style.cssText =
      'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);' +
      'background:#222;color:#fff;padding:10px 16px;border-radius:8px;' +
      'font-size:14px;z-index:1000;max-width:90vw;';
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 3500);
  }

  function setState(btn, saved) {
    btn.classList.toggle('is-active', saved);
    btn.setAttribute('aria-pressed', String(saved));
    btn.setAttribute('aria-label', saved ? 'Remove from saved' : 'Save book');
  }

  function bumpSavesCount(delta) {
    var el = document.querySelector('[data-saves-count]');
    if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
  }

  document.addEventListener('click', async function (e) {
    var btn = e.target.closest('[data-save-book]');
    if (!btn) return;
    e.preventDefault();
    if (busy.has(btn)) return;

    var id = Number(btn.dataset.bookId);
    if (!id) {
      console.error('Save book: data-book-id is missing');
      toast('Could not save the book. Please reload the page.');
      return;
    }

    var wasSaved = btn.classList.contains('is-active');
    var willSave = !wasSaved;

    // оптимистично обновляем UI, при ошибке откатываем
    setState(btn, willSave);
    busy.add(btn);
    btn.disabled = true;

    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var body = new URLSearchParams();
    var token = csrfInput();
    if (token) {
      headers['X-CSRF-Token'] = token.value;   // на случай, если токен ждут в заголовке
      body.set(token.name, token.value);       // и в теле, как у обычных форм
    }

    var url = '/api/books/' + id + '/save';
    var method = willSave ? 'POST' : 'DELETE';

    try {
      var res = await fetch(url, {
        method: method,
        credentials: 'same-origin',
        headers: headers,
        body: body
      });

      var data = null;
      try { data = await res.json(); } catch (_) { /* пустой ответ / не JSON */ }
      console.log(method, url, res.status, data); // для отладки бэка

      if (res.ok) {
        bumpSavesCount(willSave ? 1 : -1);
        btn.dispatchEvent(new CustomEvent('book:save-changed', {
          bubbles: true,
          detail: { id: id, saved: willSave }
        }));
      } else {
        setState(btn, wasSaved);
        var msg = '';
        if (data && data.errors) msg = Object.values(data.errors).flat().join('\n');
        if (!msg && data && data.message) msg = data.message;
        if (!msg) {
          msg = res.status === 403 ? 'Forbidden (verify email / CSRF?)'
              : res.status === 401 ? 'Please sign in again'
              : 'Failed to update saved books (HTTP ' + res.status + ')';
        }
        toast(msg);
      }
    } catch (err) {
      setState(btn, wasSaved);
      toast('Network error. Try again.');
    } finally {
      busy.delete(btn);
      btn.disabled = false;
    }
  });
})();