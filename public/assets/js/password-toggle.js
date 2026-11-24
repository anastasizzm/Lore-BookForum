"use strict";

/* ============================================
   Кнопка «глаз» у поля пароля.

   Разметка:
     <div class="form-field form-field--password">
       <input class="form-field__input" type="password" id="loginPassword" ...>
       <button class="form-field__toggle" type="button"
               data-password-toggle="loginPassword"
               data-label-show="Показать пароль"
               data-label-hide="Скрыть пароль">…</button>
     </div>

   Клик переключает type="password" <-> type="text" и подпись кнопки,
   состояние хранится в aria-pressed (true = пароль виден).
   ============================================ */
(function () {
  function initPasswordToggle(button) {
    if (button.dataset.passwordToggleReady === "true") return;

    const inputId = button.getAttribute("data-password-toggle");
    const input = inputId ? document.getElementById(inputId) : null;
    if (!input) return;

    button.dataset.passwordToggleReady = "true";
    button.setAttribute("aria-controls", inputId);
    button.setAttribute("aria-pressed", "false");

    function applyLabel(revealed) {
      const label = revealed
        ? button.getAttribute("data-label-hide")
        : button.getAttribute("data-label-show");
      if (!label) return;
      button.setAttribute("aria-label", label);
      button.setAttribute("title", label);
    }

    button.addEventListener("click", function () {
      const revealed = input.getAttribute("type") === "password";
      input.setAttribute("type", revealed ? "text" : "password");
      button.setAttribute("aria-pressed", String(revealed));
      applyLabel(revealed);

      // Возвращаем фокус в поле и ставим каретку в конец — иначе Safari
      // после смены type теряет позицию курсора.
      input.focus();
      try {
        const end = input.value.length;
        input.setSelectionRange(end, end);
      } catch (_) { /* setSelectionRange не работает для некоторых типов */ }
    });
  }

  function initPasswordToggles() {
    document.querySelectorAll("[data-password-toggle]").forEach(initPasswordToggle);
  }

  document.addEventListener("DOMContentLoaded", initPasswordToggles);
})();
