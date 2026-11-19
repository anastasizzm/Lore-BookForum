<?php $view->extends('auth'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
$messageText = (string) ($message ?? '');
// После регистрации (и при ошибке отправки письма) пользователь уже
// авторизован, но ещё не подтверждён. Бэк рисует эту страницу в том же
// ответе, где выставляет cookie, поэтому currentUserId здесь ещё пуст —
// ориентируемся на текст сообщения. POST /api/mail требует только логина.
$isRegisterMessage = $messageText !== '' && (
    $messageText === $view->t('common.email.confirm_msg') ||
    $messageText === $view->t('errors.verify.send_fail')
);
$showResend = empty($statusCode) && $isRegisterMessage;
?>

<?php $view->startBlock('title'); ?><?= $tr('common.message.title') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap">
  <link rel="stylesheet" href="/assets/css/pages/login.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <header class="login-card__header login-card__header--center">
    <span class="login-card__logo">
      <img src="/assets/img/logo.svg" alt="<?= $tr('common.auth.logo_alt') ?>" width="56" height="56">
    </span>
  </header>

  <div class="login-message"
       <?= $showResend ? 'data-verify-result data-verify-success="0"' : '' ?>>
    <?php if (!empty($statusCode)): ?>
      <div class="login-message__status"><?= $view->e((string) $statusCode) ?></div>
    <?php endif; ?>

    <p class="login-message__text"><?= $view->e($message ?? '') ?></p>

    <?php if (!empty($actionUrl) && !empty($actionTitle)): ?>
      <a class="btn btn--primary" href="<?= $view->e($actionUrl) ?>">
        <?= $view->e($actionTitle) ?>
      </a>
    <?php endif; ?>

    <?php if ($showResend): ?>
      <!-- Повторная отправка письма подтверждения: POST /api/mail
           (api.sendmail, AuthPolicy::Auth). Токен из письма не нужен —
           это отличает форму от auth/verify-result (data-verify-no-token). -->
      <form class="login-message__form"
            data-verify-resend
            data-verify-no-token="1"
            method="post"
            action="<?= $view->e($view->url('api.sendmail')) ?>">
        <?= $view->csrfField() ?>
        <button type="submit" class="btn btn--secondary" data-verify-retry>
          <?= $tr('common.auth.resend') ?>
        </button>
      </form>
      <div class="messages" data-verify-messages hidden></div>
    <?php endif; ?>
  </div>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/verify-result.js"></script>
<?php $view->endBlock('scripts'); ?>