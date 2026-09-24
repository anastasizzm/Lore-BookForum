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

  // ===== Бургер-меню и Сайдбар =====
  const burger = document.querySelector('[data-burger]') || document.querySelector('.burger');
  const overlay = document.querySelector('[data-sidebar-overlay]');
  const sidebar = document.querySelector('[data-sidebar]');
  const body = document.body;

  function closeMenu() {
    body.classList.remove('is-menu-open');
  }

  function openMenu() {
    body.classList.add('is-menu-open');
  }

  // Клик по бургеру — только открывает меню
  if (burger) {
    burger.addEventListener('click', function (e) {
      e.stopPropagation();
      openMenu();
    });
  }

  // Клик по серой зоне (overlay) — закрывает меню
  if (overlay) {
    overlay.addEventListener('click', closeMenu);
  }

  // Клик по любой ссылке внутри сайдбара — закрывает меню (на мобилках)
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
  }

  // Закрытие по нажатию клавиши Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeMenu();
    }
  });
});