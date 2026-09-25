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

  // ===== Бургер-меню (Делегирование событий) =====
  const body = document.body;
  const overlay = document.querySelector('.sidebar-overlay');
  const sidebar = document.querySelector('[data-sidebar]');

  function closeMenu() {
    body.classList.remove('is-menu-open');
  }

  function openMenu() {
    body.classList.add('is-menu-open');
  }

  // Вешаем клик на весь документ, но отлавливаем именно клик по бургеру
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
});