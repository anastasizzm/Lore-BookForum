<?php $view->extends('auth'); ?>
<?php $tr = static fn(string $k, array $p = []): string => $view->e($view->t($k, $p)); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.auth.verify_title') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap">
  <link rel="stylesheet" href="/assets/css/login.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <header class="login-card__header">
    <span class="login-card__logo">
      <img src="/assets/img/logo.svg" alt="<?= $tr('common.auth.logo_alt') ?>" width="56" height="56">
    </span>
  </header>

  <div class="login-message login-message--<?= !empty($success) ? 'success' : 'error' ?>"
       data-verify-result
       data-verify-success="<?= !empty($success) ? '1' : '0' ?>">

    <p class="login-message__text"><?= $view->e($message ?? '') ?></p>

    <?php if (!empty($success)): ?>
      <a class="btn btn--primary" href="<?= $view->url('login') ?>">
        <?= $tr('common.auth.sign_in') ?>
      </a>
    <?php else:
      /*
        Resend verification.
        Токен подтверждения приходит в адресе страницы (/auth/verify/{token}) —
        контроллер его во view не передаёт, поэтому достаём его из URL и кладём
        в скрытое поле формы `token`: бэк (POST /api/mail) читает именно его.
        Адрес для resend берём из роутера ($view->url), без хардкода.
      */
      $path  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
      $match = [];
      $verifyToken = preg_match('#^/auth/verify/([^/]+)#', $path, $match) === 1
        ? rawurldecode($match[1])
        : '';
    ?>
      <form class="login-message__form"
            data-verify-resend
            method="post"
            action="<?= $view->e($view->url('api.sendmail')) ?>">
        <?= $view->csrfField() ?>
        <input type="hidden" name="token" value="<?= $view->e($verifyToken) ?>">

        <button type="submit" class="btn btn--primary" data-verify-retry>
          <?= $tr('common.auth.resend') ?>
        </button>
      </form>
    <?php endif; ?>

    <!-- Сюда JS будет вставлять плашку при ошибке -->
    <div class="messages" data-verify-messages hidden></div>

  </div>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/verify-result.js"></script>
<?php $view->endBlock('scripts'); ?>