"use strict";

function initPasswordEmailPage() {
  const form = document.getElementById("passwordEmailForm");
  if (!form) return;

  const email = document.getElementById("passwordEmail");

  // errors are shown only after the first "Send reset link" click
  let submitAttempted = false;

  function setError(input, messageId, text) {
    const invalid = text !== "";
    input.classList.toggle("form-field__input--invalid", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    document.getElementById(messageId).textContent = text;
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
    if (!validateEmail()) event.preventDefault();
  });
}

document.addEventListener("DOMContentLoaded", initPasswordEmailPage);
