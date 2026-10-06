<?php $view->extends('auth'); ?>

<?php $view->startBlock('title'); ?>Reset password — Lore<?php $view->endBlock('title'); ?>

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
    <h1 class="login-card__title">Forgot password?</h1>
  </header>

  <p class="login-card__text">
    Enter the email you signed up with and we'll send you a link to set a new password.
  </p>

  <?php
    // ошибки, у которых на форме нет своего поля (всё новое, что может прийти с бэка)
    $unboundErrors = [];
    foreach (($errors ?? []) as $field => $list) {
      if ($field === 'email') continue;
      foreach ((array) $list as $err) $unboundErrors[] = $err;
    }
  ?>
  <?php if (!empty($unboundErrors)): ?>
    <div class="messages">
      <?php foreach ($unboundErrors as $err): ?>
        <div class="message message--error">
          <div class="message__body"><?= $view->e($err) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!--
    Контракт с бэком:
      POST /auth/password-reset (url('password.email.submit')), application/x-www-form-urlencoded
        поля: email, csrf-поле
      успех: страница message "The link to reset your password was sent to your email"
             (в письме — ссылка /auth/password-reset/{token})
      ошибка: этот же шаблон с $errors['email'] / $innerMessages
  -->
  <form class="login-form" id="passwordEmailForm" action="<?= $view->url('password.email.submit') ?>" method="POST" novalidate>
    <?= $view->csrfField() ?>

    <div class="form-field">
      <label class="visually-hidden" for="passwordEmail">Email</label>
      <input class="form-field__input" type="email" id="passwordEmail" name="email"
             value="<?= $view->e($form['email'] ?? '') ?>"
             placeholder="Enter email" autocomplete="email" maxlength="254"
             aria-describedby="passwordEmailError">
      <p class="form-field__error" id="passwordEmailError" aria-live="polite"></p>
      <?php foreach (($errors['email'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary" type="submit">Send reset link</button>

    <div class="login-form__links login-form__links--center">
      <a class="link" href="<?= $view->url('login') ?>">Back to sign in</a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/password-email.js"></script>
<?php $view->endBlock('scripts'); ?>
