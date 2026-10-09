"use strict";

function initPasswordResetPage() {
  const form = document.getElementById("passwordResetForm");
  if (!form) return;

  const password = document.getElementById("resetPassword");
  const passwordConfirm = document.getElementById("resetPasswordConfirm");

  // errors are shown only after the first "Save new password" click
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

  function validatePassword() {
    const ok = LoreValidators.isPassword(password.value);
    let message = "";
    if (!ok) {
      message =
        password.value.length < LoreValidators.PASSWORD_MIN_LENGTH
          ? `Password must be at least ${LoreValidators.PASSWORD_MIN_LENGTH} characters`
          : "Invalid password";
    }
    setError(password, "resetPasswordError", message);
    return ok;
  }

  function validatePasswordConfirm() {
    const ok = passwordConfirm.value !== "" && passwordConfirm.value === password.value;
    setError(
      passwordConfirm,
      "resetPasswordConfirmError",
      ok ? "" : "Passwords are different"
    );
    return ok;
  }

  password.addEventListener("input", () => {
    if (submitAttempted) {
      validatePassword();
      if (passwordConfirm.value !== "") validatePasswordConfirm();
    }
  });
  passwordConfirm.addEventListener("input", () => {
    if (submitAttempted) validatePasswordConfirm();
  });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    // both checks run (no short-circuit) so both errors show at once
    const passwordOk = validatePassword();
    const confirmOk = validatePasswordConfirm();
    if (!passwordOk || !confirmOk) {
      event.preventDefault();
      showErrors();
    }
  });
}

document.addEventListener("DOMContentLoaded", initPasswordResetPage);