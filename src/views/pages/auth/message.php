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
    <h1 class="login-card__title"><?= $view->e($title ?? 'Check your email') ?></h1>
  </header>

  <?php if (!empty($innerMessages)): ?>
    <div class="messages">
      <?php foreach ($innerMessages as $msg): ?>
        <div class="message message--<?= $view->e($msg['type']) ?>">
          <?php if (!empty($msg['title'])): ?>
            <div class="message__title"><?= $view->e($msg['title']) ?></div>
          <?php endif; ?>
          <div class="message__body"><?= $view->e($msg['body']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="login-message">
    <p class="login-message__text"><?= $view->e($body ?? '') ?></p>

    <?php if (!empty($actionLabel)): ?>
      <a class="btn btn--primary" href="<?= $view->e($actionUrl ?? $view->url('login')) ?>">
        <?= $view->e($actionLabel) ?>
      </a>
    <?php endif; ?>
  </div>

<?php $view->endBlock('content'); ?>