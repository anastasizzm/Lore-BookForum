"use strict";

function initPasswordEmailPage() {
  const form = document.getElementById("passwordEmailForm");
  if (!form) return;

  const email = document.getElementById("passwordEmail");

  // errors are shown only after the first "Send reset link" click
  let submitAttempted = false;

  // Тексты ошибок показываем общей плашкой (messages.js), а не красным текстом
  // под полем: у поля остаётся красная рамка и aria-invalid.
  const errors = {};

  function setError(input, messageId, text) {
    const invalid = text !== "";
    input.classList.toggle("form-field__input--invalid", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    errors[messageId] = text;
    // без messages.js — запасной вариант: старый текст под полем
    document.getElementById(messageId).textContent = window.Messages ? "" : text;
  }

  // Все текущие ошибки формы — одной плашкой (вызывается при отправке)
  function showErrors() {
    const list = Object.values(errors).filter(Boolean);
    if (list.length && window.Messages) {
      window.Messages.show(list.join("\n"), { type: "error" });
    }
  }

  function validateEmail() {
    const ok = LoreValidators.isEmail(email.value);
    setError(email, "passwordEmailError", ok ? "" : "Invalid email");
    return ok;
  }

  email.addEventListener("input", () => {
    if (submitAttempted) validateEmail();
  });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    if (!validateEmail()) {
      event.preventDefault();
      showErrors();
    }
  });
}

document.addEventListener("DOMContentLoaded", initPasswordEmailPage);