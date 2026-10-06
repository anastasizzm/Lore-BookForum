<?php $view->extends('auth'); ?>

<?php $view->startBlock('title'); ?>Set new password — Lore<?php $view->endBlock('title'); ?>

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
    <h1 class="login-card__title">Set new password</h1>
  </header>

  <p class="login-card__text">
    Create a new password for your account. It must be at least 8 characters long.
  </p>

  <!--
    Контракт с бэком:
      POST /password/reset, application/x-www-form-urlencoded
        поля: token, password, password_confirm, csrf-поле
      успех: редирект на /login с плашкой "Password updated"
      ошибки: этот же шаблон с $errors[...] / $innerMessages;
              невалидный/просроченный token -> редирект на /password/email
    token приходит из ссылки в письме (/password/reset?token=...),
    поэтому кладём его в hidden-поле.
    Роут и обработчик добавит бэкенд.
  -->
  <form class="login-form" id="passwordResetForm" action="<?= $view->url('password.reset.submit') ?>" method="POST" novalidate>
    <?= $view->csrfField() ?>

    <input type="hidden" name="token" value="<?= $view->e($token ?? ($_GET['token'] ?? '')) ?>">

    <div class="form-field">
      <label class="visually-hidden" for="resetPassword">New password</label>
      <input class="form-field__input" type="password" id="resetPassword" name="password"
             placeholder="Enter new password" autocomplete="new-password" maxlength="64"
             aria-describedby="resetPasswordError">
      <p class="form-field__error" id="resetPasswordError" aria-live="polite"></p>
      <?php foreach (($errors['password'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="resetPasswordConfirm">Confirm new password</label>
      <input class="form-field__input" type="password" id="resetPasswordConfirm" name="password_confirm"
             placeholder="Repeat new password" autocomplete="new-password" maxlength="64"
             aria-describedby="resetPasswordConfirmError">
      <p class="form-field__error" id="resetPasswordConfirmError" aria-live="polite"></p>
      <?php foreach (($errors['password_confirm'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary" type="submit">Save new password</button>

    <div class="login-form__links login-form__links--center">
      <a class="link" href="<?= $view->url('login') ?>">Back to sign in</a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/password-reset.js"></script>
<?php $view->endBlock('scripts'); ?>
