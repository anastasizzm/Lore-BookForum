"use strict";

function initRegisterPage() {
  const form = document.getElementById("registerForm");
  const email = document.getElementById("registerEmail");
  const nickname = document.getElementById("registerNickname");
  const password = document.getElementById("registerPassword");
  const passwordConfirm = document.getElementById("registerPasswordConfirm");

  // errors are shown only after the first "Sign up" click;
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
    setError(email, "registerEmailError", ok ? "" : "Invalid email");
    return ok;
  }

  function validateNickname() {
    const ok = nickname.value.trim().length > 0;
    setError(nickname, "registerNicknameError", ok ? "" : "Invalid nickname");
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
    setError(password, "registerPasswordError", message);
    return ok;
  }

  function validatePasswordConfirm() {
    const ok = passwordConfirm.value !== "" && passwordConfirm.value === password.value;
    setError(
      passwordConfirm,
      "registerPasswordConfirmError",
      ok ? "" : "Passwords are different"
    );
    return ok;
  }

  // after a failed attempt, re-check while typing so errors
  // disappear as soon as the values become valid
  email.addEventListener("input", () => {
    if (submitAttempted) validateEmail();
  });
  nickname.addEventListener("input", () => {
    if (submitAttempted) validateNickname();
  });
  password.addEventListener("input", () => {
    if (submitAttempted) {
      validatePassword();
      // confirm field depends on password's value, so re-check it too
      if (passwordConfirm.value !== "") validatePasswordConfirm();
    }
  });
  passwordConfirm.addEventListener("input", () => {
    if (submitAttempted) validatePasswordConfirm();
  });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    // all three checks run (no short-circuit) so every error shows at once
    const emailOk = validateEmail();
    const nicknameOk = validateNickname();
    const passwordOk = validatePassword();
    const confirmOk = validatePasswordConfirm();
    if (!emailOk || !nicknameOk || !passwordOk || !confirmOk) event.preventDefault();
  });
}

document.addEventListener("DOMContentLoaded", initRegisterPage);
