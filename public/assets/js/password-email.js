"use strict";

function initPasswordEmailPage() {
  const t = (key, params) => window.LoreI18n ? LoreI18n.t(key, params) : key;

  const form = document.getElementById("passwordEmailForm");
  if (!form) return;

  const email = document.getElementById("passwordEmail");
  let submitAttempted = false;
  const errors = {};

  function setError(input, messageId, text) {
    const invalid = text !== "";
    input.classList.toggle("form-field__input--invalid", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    errors[messageId] = text;
    document.getElementById(messageId).textContent = window.Messages ? "" : text;
  }

  function showErrors() {
    const list = Object.values(errors).filter(Boolean);
    if (list.length && window.Messages) {
      window.Messages.show(list.join("\n"), { type: "error" });
    }
  }

  function validateEmail() {
    const ok = LoreValidators.isEmail(email.value);
    setError(email, "passwordEmailError", ok ? "" : t("common.js.invalid_email"));
    return ok;
  }

  email.addEventListener("input", () => { if (submitAttempted) validateEmail(); });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    if (!validateEmail()) {
      event.preventDefault();
      showErrors();
    }
  });
}

document.addEventListener("DOMContentLoaded", initPasswordEmailPage);