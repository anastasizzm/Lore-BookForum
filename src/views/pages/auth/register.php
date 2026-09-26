<?php $view->extends('auth'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap">
  <link rel="stylesheet" href="/assets/css/login.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <header class="login-card__header">
    <span class="login-card__logo">
      <img src="/assets/img/logo.svg" alt="Lore logo" width="56" height="56">
    </span>
    <h1 class="login-card__title">Create account</h1>
  </header>

  <form class="login-form" id="registerForm" action="/register" method="POST" novalidate>
    <?= $view->csrfField() ?>

    <div class="form-field">
      <label class="visually-hidden" for="registerEmail">Email</label>
      <input class="form-field__input" type="email" id="registerEmail" name="email"
             value="<?= $view->e($email ?? '') ?>"
             placeholder="Enter email" autocomplete="username" maxlength="254"
             aria-describedby="registerEmailError">
      <p class="form-field__error" id="registerEmailError" aria-live="polite"></p>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerNickname">Nickname</label>
      <input class="form-field__input" type="text" id="registerNickname" name="nickname"
             value="<?= $view->e($nickname ?? '') ?>"
             placeholder="Enter nickname" autocomplete="nickname" maxlength="32"
             aria-describedby="registerNicknameError">
      <p class="form-field__error" id="registerNicknameError" aria-live="polite"></p>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerPassword">Password</label>
      <input class="form-field__input" type="password" id="registerPassword" name="password"
             placeholder="Enter password" autocomplete="new-password" maxlength="64"
             aria-describedby="registerPasswordError">
      <p class="form-field__error" id="registerPasswordError" aria-live="polite"></p>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerPasswordConfirm">Confirm password</label>
      <input class="form-field__input" type="password" id="registerPasswordConfirm" name="password_confirm"
             placeholder="Repeat password" autocomplete="new-password" maxlength="64"
             aria-describedby="registerPasswordConfirmError">
      <p class="form-field__error" id="registerPasswordConfirmError" aria-live="polite"></p>
    </div>

    <button class="btn btn--primary" type="submit">Sign in</button>

    <div class="login-form__links login-form__links--center">
      <a class="link" href="<?= $view->url('login') ?>">Already have an account? Sign in</a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/register.js"></script>
<?php $view->endBlock('scripts'); ?>
