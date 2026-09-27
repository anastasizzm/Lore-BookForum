"use strict";

const LoreValidators = {
  EMAIL_MAX_LENGTH: 254,
  PASSWORD_MIN_LENGTH: 8,
  PASSWORD_MAX_LENGTH: 64,
  LOGIN_MIN_LENGTH: 3,
  LOGIN_MAX_LENGTH: 254,

  isEmail(value) {
    const email = value.trim();
    if (email.length === 0 || email.length > this.EMAIL_MAX_LENGTH) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  },

  // username: буквы, цифры, . _ -
  isUsername(value) {
    const name = value.trim();
    if (name.length < this.LOGIN_MIN_LENGTH) return false;
    if (name.length > 32) return false;
    return /^[A-Za-z0-9._-]+$/.test(name);
  },

  // login = email ИЛИ username
  isLogin(value) {
    const v = value.trim();
    if (v.length === 0 || v.length > this.LOGIN_MAX_LENGTH) return false;
    return this.isEmail(v) || this.isUsername(v);
  },

  isPassword(value) {
    return (
      value.length >= this.PASSWORD_MIN_LENGTH &&
      value.length <= this.PASSWORD_MAX_LENGTH
    );
  },
};