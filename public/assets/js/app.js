document.addEventListener('DOMContentLoaded', () => {
  // ===== Дропдауны =====
  document.querySelectorAll('[data-dropdown]').forEach(function (dd) {
    var trigger = dd.querySelector('[data-dropdown-trigger]');
    if (!trigger) return;

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      dd.classList.toggle('is-open');
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('[data-dropdown].is-open').forEach(dd => {
      dd.classList.remove('is-open');
    });
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