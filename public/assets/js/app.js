document.addEventListener('DOMContentLoaded', () => {

  // ===== 1. Filters panel: init state from ?f= flag on page load =====
  (function initFilterPanelFromFlag() {
    const panel = document.querySelector('[data-filter-panel]');
    const btn   = document.querySelector('[data-filter-toggle]');
    if (!panel) return;

    const url = new URL(window.location.href);
    const f   = url.searchParams.get('f');

    const isOpen = !panel.hidden;

    if (btn) btn.classList.toggle('is-active', isOpen);

    if (isOpen && f !== 'open') {
      url.searchParams.set('f', 'open');
      window.history.replaceState({}, '', url.toString());
    } else if (!isOpen && f === 'open') {
      url.searchParams.set('f', 'closed');
      window.history.replaceState({}, '', url.toString());
    }

    if (isOpen && typeof updateSegmentIndicator === 'function') {
      panel.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
    }
  })();


  // ===== 2. Dropdowns =====
  document.querySelectorAll('[data-dropdown]').forEach(dd => {
    const trigger = dd.querySelector('[data-dropdown-trigger]');
    if (!trigger) return;

    trigger.addEventListener('click', e => {
      e.stopPropagation();
      const willOpen = !dd.classList.contains('is-open');

      document.querySelectorAll('[data-dropdown].is-open').forEach(other => {
        if (other !== dd) other.classList.remove('is-open');
      });

      dd.classList.toggle('is-open', willOpen);
    });

    dd.querySelectorAll('.dropdown__item').forEach(item => {
      item.addEventListener('click', () => {
        dd.classList.remove('is-open');
      });
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('[data-dropdown].is-open')
      .forEach(dd => dd.classList.remove('is-open'));
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('[data-dropdown].is-open')
        .forEach(dd => dd.classList.remove('is-open'));
    }
  });


  // ===== 3. Filters panel toggle =====
  document.querySelectorAll('[data-filter-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const panel = document.querySelector('[data-filter-panel]');
      if (!panel) return;

      panel.hidden = !panel.hidden;
      btn.classList.toggle('is-active', !panel.hidden);

      const url = new URL(window.location.href);
      if (panel.hidden) {
        url.searchParams.set('f', 'closed');
      } else {
        url.searchParams.set('f', 'open');
        if (typeof updateSegmentIndicator === 'function') {
          panel.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
        }
      }
      window.history.replaceState({}, '', url.toString());
    });
  });


  // ===== 4. Preserve ?f=open on link clicks inside panel =====
  document.addEventListener('click', function (e) {
    const panel = document.querySelector('[data-filter-panel]');
    if (!panel || panel.hidden) return;

    const link = e.target.closest('[data-filter-panel] a[href]');
    if (!link) return;

    const href = link.getAttribute('href') || '';
    if (href === '' || href.startsWith('#')) return;

    const url = new URL(link.href, window.location.origin);
    url.searchParams.set('f', 'open');
    link.href = url.toString();
  }, true);


  // ===== 5. Burger menu =====
  const body = document.body;
  const overlay = document.querySelector('.sidebar-overlay');
  const sidebar = document.querySelector('[data-sidebar]');

  function closeMenu() { body.classList.remove('is-menu-open'); }
  function openMenu()  { body.classList.add('is-menu-open'); }

  document.addEventListener('click', function (e) {
    const burgerTarget = e.target.closest('.burger, [data-burger]');
    if (burgerTarget) {
      e.preventDefault();
      e.stopPropagation();
      if (body.classList.contains('is-menu-open')) closeMenu();
      else openMenu();
    }
  });

  if (overlay) {
    overlay.addEventListener('click', closeMenu);
  }
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenu();
  });


  // ===== 6. Sidebar expansion on desktop =====
  if (sidebar) {
    const desktop = window.matchMedia('(min-width: 769px)');

    const setExpanded = (value) => {
      sidebar.classList.toggle('is-expanded', value);
      body.classList.toggle('is-sidebar-expanded', value);
    };

    sidebar.addEventListener('click', (e) => {
      if (!desktop.matches) return;
      if (e.target.closest('a, button, .settings-menu')) return;
      setExpanded(!sidebar.classList.contains('is-expanded'));
    });

    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target)) setExpanded(false);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setExpanded(false);
    });

    sidebar.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => setExpanded(false));
    });

    desktop.addEventListener('change', (e) => {
      if (!e.matches) setExpanded(false);
    });
  }


  // ===== 7. Settings menu =====
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

    menu.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', closeSettings);
    });
  })();
});


/* ===== Save book / article ===== */
(function () {
  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  var busy = new WeakSet();

  function csrfValue() {
    var fromCookie = window.LoreCsrf ? window.LoreCsrf.token() : '';
    if (fromCookie) return fromCookie;
    var el = document.querySelector('input[type="hidden"][name="_token"]');
    return el ? el.value : '';
  }

  function toast(text) {
    if (window.Messages) { window.Messages.show(text, { type: 'error' }); return; }
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

  function setState(btn, saved, type) {
    btn.classList.toggle('is-active', saved);
    btn.setAttribute('aria-pressed', String(saved));
    var key = saved ? 'js.unsave' : (type === 'article' ? 'js.save_article' : 'js.save_book');
    btn.setAttribute('aria-label', t(key));
  }

  function bumpSavesCount(delta) {
    var el = document.querySelector('[data-saves-count]');
    if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
  }

  document.addEventListener('click', async function (e) {
    var btn = e.target.closest('[data-save-book], [data-save-article]');
    if (!btn) return;
    e.preventDefault();
    if (busy.has(btn)) return;

    var type = btn.hasAttribute('data-save-article') ? 'article' : 'book';
    var id = Number(type === 'article' ? btn.dataset.articleId : btn.dataset.bookId);
    if (!id) {
      console.error('Save ' + type + ': publication id attribute is missing');
      toast(t('errors.common.save_failed_reload'));
      return;
    }

    var wasSaved = btn.classList.contains('is-active');
    var willSave = !wasSaved;

    setState(btn, willSave, type);
    busy.add(btn);
    btn.disabled = true;

    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var body = new URLSearchParams();
    var token = csrfValue();
    if (token) {
      headers['X-CSRF-Token'] = token;
      body.set('_token', token);
    }

    var url = btn.dataset.saveUrl || ('/api/' + type + 's/' + id + '/save');
    var method = willSave ? 'POST' : 'DELETE';

    try {
      var res = await fetch(url, {
        method: method,
        credentials: 'same-origin',
        headers: headers,
        body: body
      });

      var raw = '';
      var data = null;
      try { raw = await res.text(); data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }
      var htmlBody = /^\s*</.test(raw);

      if (res.ok && !htmlBody) {
        bumpSavesCount(willSave ? 1 : -1);
        btn.dispatchEvent(new CustomEvent('save:changed', {
          bubbles: true,
          detail: { id: id, saved: willSave, type: type }
        }));
        btn.dispatchEvent(new CustomEvent(type + ':save-changed', {
          bubbles: true,
          detail: { id: id, saved: willSave }
        }));
      } else {
        setState(btn, wasSaved, type);
        var fallbackMsg = t('errors.common.request_failed', { status: res.status });
        toast(window.Messages
          ? window.Messages.describe(res.status, data, raw, fallbackMsg)
          : fallbackMsg);
      }
    } catch (err) {
      setState(btn, wasSaved, type);
      toast(t('errors.common.network_error'));
    } finally {
      busy.delete(btn);
      btn.disabled = false;
    }
  });
})();

/* ===== Cover image fallback ===== */
(function () {
  var BOX = '.card-book__cover, .book-details__cover, .profile-publications__cover';

  function mark(img) {
    if (img.classList.contains('card-feed__book-thumb')) {
      var stub = document.createElement('span');
      stub.className = img.className + ' cover--empty';
      stub.setAttribute('aria-hidden', 'true');
      img.replaceWith(stub);
      return;
    }
    var box = img.closest ? img.closest(BOX) : null;
    if (box) box.classList.add('cover--empty');
  }

  document.addEventListener('error', function (e) {
    if (e.target && e.target.tagName === 'IMG') mark(e.target);
  }, true);

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll(
      '.card-book__cover img, .book-details__cover img, .profile-publications__cover img, img.card-feed__book-thumb'
    ).forEach(function (img) {
      if (img.complete && img.naturalWidth === 0) mark(img);
    });
  });
})();