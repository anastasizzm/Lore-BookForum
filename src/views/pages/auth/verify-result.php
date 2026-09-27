<?php $view->extends('auth'); ?>

<?php $view->startBlock('title'); ?>Email verification<?php $view->endBlock('title'); ?>

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
  </header>

  <div class="login-message login-message--<?= !empty($success) ? 'success' : 'error' ?>"
       data-verify-result
       data-verify-success="<?= !empty($success) ? '1' : '0' ?>">

    <p class="login-message__text"><?= $view->e($message ?? '') ?></p>

    <?php if (!empty($success)): ?>
      <a class="btn btn--primary" href="<?= $view->url('login') ?>">
        Sign in
      </a>
    <?php else: ?>
      <button type="button"
              class="btn btn--primary"
              data-verify-retry
              data-resend-url="/api/verify/resend"
              data-message-url="#">
        Resend verification
      </button>
    <?php endif; ?>

    <!-- Сюда JS будет вставлять плашку при ошибке -->
    <div class="messages" data-verify-messages hidden></div>

  </div>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/verify-result.js"></script>
<?php $view->endBlock('scripts'); ?>