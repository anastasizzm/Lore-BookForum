document.addEventListener('DOMContentLoaded', () => {

  // ===== 1. Filters panel: init state from ?f= flag on page load =====
  (function initFilterPanelFromFlag() {
    const panel = document.querySelector('[data-filter-panel]');
    const btn   = document.querySelector('[data-filter-toggle]');
    if (!panel) return;

    const url = new URL(window.location.href);
    const f   = url.searchParams.get('f');   // 'open' | 'closed' | null

    // Server already renders panel as hidden/open via $filter_open.
    // But we also sync the button visual state + URL.
    const isOpen = !panel.hidden;

    if (btn) btn.classList.toggle('is-active', isOpen);

    // Ensure the URL reflects current state
    if (isOpen && f !== 'open') {
      url.searchParams.set('f', 'open');
      window.history.replaceState({}, '', url.toString());
    } else if (!isOpen && f === 'open') {
      // Server rendered as closed but URL says open — trust server, fix URL
      url.searchParams.set('f', 'closed');
      window.history.replaceState({}, '', url.toString());
    }

    // Recalculate segmented indicators if panel is open on load
    if (isOpen && typeof updateSegmentIndicator === 'function') {
      panel.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
    }
  })();


  // ===== 2. Dropdowns: open, auto-close siblings, close on outside / Escape =====
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


  // ===== 3. Filters panel toggle (with ?f= flag in URL) =====
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


  // ===== 4. Filters panel: preserve ?f=open on link clicks inside panel =====
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
  }, true);   // capture — intercept before navigation


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
      openMenu();
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
  })();
});


/* ===== Save book: POST/DELETE /api/books/{id}/save ===== */
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

    setState(btn, willSave);
    busy.add(btn);
    btn.disabled = true;

    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var body = new URLSearchParams();
    var token = csrfInput();
    if (token) {
      headers['X-CSRF-Token'] = token.value;
      body.set(token.name, token.value);
    }

    var url = btn.dataset.saveUrl || ('/api/books/' + id + '/save');
    var method = willSave ? 'POST' : 'DELETE';

    try {
      var res = await fetch(url, {
        method: method,
        credentials: 'same-origin',
        headers: headers,
        body: body
      });

      var data = null;
      try { data = await res.json(); } catch (_) {}

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