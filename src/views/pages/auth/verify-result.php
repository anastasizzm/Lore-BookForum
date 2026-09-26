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
    <h1 class="login-card__title">
      <?= !empty($success) ? 'Email verified' : 'Verification failed' ?>
    </h1>
  </header>

  <div class="login-message login-message--<?= !empty($success) ? 'success' : 'error' ?>">
    <p class="login-message__text"><?= $view->e($body ?? '') ?></p>

    <a class="btn btn--primary" href="<?= $view->url('login') ?>">
      <?= !empty($success) ? 'Sign in' : 'Try again' ?>
    </a>
  </div>

<?php $view->endBlock('content'); ?>