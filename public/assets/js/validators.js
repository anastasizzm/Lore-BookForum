"use strict";

// Shared validation rules. Plain global (no ES modules) so pages
// also work when opened directly from disk (file://).
const LoreValidators = {
  EMAIL_MAX_LENGTH: 254, // practical limit from RFC 5321
  PASSWORD_MIN_LENGTH: 8,
  PASSWORD_MAX_LENGTH: 64,

  // exactly one "@", something before it, a domain with a dot after it, no spaces
  isEmail(value) {
    const email = value.trim();
    if (email.length === 0 || email.length > this.EMAIL_MAX_LENGTH) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  },

  isPassword(value) {
    return (
      value.length >= this.PASSWORD_MIN_LENGTH &&
      value.length <= this.PASSWORD_MAX_LENGTH
    );
  },
};
