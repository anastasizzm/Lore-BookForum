<?php $view->extends('auth'); ?>

<?php $view->startBlock('title'); ?>Message<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap">
  <link rel="stylesheet" href="/assets/css/login.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <header class="login-card__header login-card__header--center">
    <span class="login-card__logo">
      <img src="/assets/img/logo.svg" alt="Lore logo" width="56" height="56">
    </span>
  </header>

  <div class="login-message">
    <?php if (!empty($statusCode)): ?>
      <div class="login-message__status"><?= $view->e((string) $statusCode) ?></div>
    <?php endif; ?>

    <p class="login-message__text"><?= $view->e($message ?? '') ?></p>

    <?php if (!empty($actionUrl) && !empty($actionTitle)): ?>
      <a class="btn btn--primary" href="<?= $view->e($actionUrl) ?>">
        <?= $view->e($actionTitle) ?>
      </a>
    <?php endif; ?>
  </div>

<?php $view->endBlock('content'); ?>