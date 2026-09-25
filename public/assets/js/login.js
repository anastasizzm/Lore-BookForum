"use strict";

function initLoginPage() {
  const form = document.getElementById("loginForm");
  const email = document.getElementById("loginEmail");
  const password = document.getElementById("loginPassword");

  // errors are shown only after the first "Sign in" click;
  // before that, focusing or leaving a field never triggers validation
  let submitAttempted = false;

  function setError(input, messageId, text) {
    const invalid = text !== "";
    input.classList.toggle("form-field__input--invalid", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    document.getElementById(messageId).textContent = text;
  }

  function validateEmail() {
    const ok = LoreValidators.isEmail(email.value);
    setError(email, "loginEmailError", ok ? "" : "Invalid email");
    return ok;
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
    setError(password, "loginPasswordError", message);
    return ok;
  }

  // after a failed attempt, re-check while typing so the error
  // disappears as soon as the value becomes valid
  email.addEventListener("input", () => {
    if (submitAttempted) validateEmail();
  });
  password.addEventListener("input", () => {
    if (submitAttempted) validatePassword();
  });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    // both checks run (no short-circuit) so both errors show at once
    const emailOk = validateEmail();
    const passwordOk = validatePassword();
    if (!emailOk || !passwordOk) event.preventDefault();
  });
}

document.addEventListener("DOMContentLoaded", initLoginPage);
