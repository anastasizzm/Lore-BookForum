<?php $view->extends('auth'); ?>

<?php $view->startBlock('title'); ?>Sign in to Lore<?php $view->endBlock('title'); ?>

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
    <h1 class="login-card__title">Sign in to Lore</h1>
  </header>

  <form class="login-form" id="loginForm" action="<?= $view->url('login') ?>" method="POST" novalidate>
    <?= $view->csrfField() ?>

    <div class="form-field">
      <label class="visually-hidden" for="loginLogin">Login or email</label>
      <input class="form-field__input" type="text" id="loginLogin" name="login"
             value="<?= $view->e($form['login'] ?? '') ?>"
             placeholder="Enter login or email" autocomplete="username" maxlength="254"
             aria-describedby="loginLoginError">
      <p class="form-field__error" id="loginLoginError" aria-live="polite"></p>
      <?php foreach (($errors['login'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="loginPassword">Password</label>
      <input class="form-field__input" type="password" id="loginPassword" name="password"
             placeholder="Enter password" autocomplete="current-password" maxlength="64"
             aria-describedby="loginPasswordError">
      <p class="form-field__error" id="loginPasswordError" aria-live="polite"></p>
      <?php foreach (($errors['password'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary" type="submit">Sign in</button>

    <div class="login-form__links">
      <a class="link" href="#" title="Coming soon">Forgot password?</a>
      <a class="link" href="<?= $view->url('register') ?>">Don't have account?</a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/login.js"></script>
<?php $view->endBlock('scripts'); ?>