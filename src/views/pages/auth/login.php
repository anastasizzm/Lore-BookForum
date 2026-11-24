<?php $view->extends('auth'); ?>
<?php $tr = static fn(string $k, array $p = []): string => $view->e($view->t($k, $p)); ?>

<?php
// Сохраняем ?lang= во всех ссылках и в action формы — бэк читает локаль
// из query (LocaleMiddleware), без этого он уйдёт на Accept-Language.
$langQuery = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : null);

$withLang = static function (string $path) use ($langQuery): string {
    if ($langQuery === null || $langQuery === '') return $path;
    // $view->url() already appends ?lang=; never add it twice.
    if (preg_match('/(?:[?&])lang=/', $path)) return rtrim($path, '&');
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . urlencode($langQuery);
};
?>

<?php $view->startBlock('title'); ?><?= $tr('common.auth.login_title') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap">
  <link rel="stylesheet" href="<?= $view->asset('css/pages/login.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <header class="login-card__header">
    <span class="login-card__logo">
      <img src="/assets/img/logo.svg" alt="<?= $tr('common.auth.logo_alt') ?>" width="56" height="56">
    </span>
    <h1 class="login-card__title"><?= $tr('common.auth.login_title') ?></h1>
  </header>

  <form class="login-form" id="loginForm"
        action="<?= $view->e($withLang($view->url('login'))) ?>"
        method="POST" novalidate>
    <?= $view->csrfField() ?>

    <div class="form-field">
      <label class="visually-hidden" for="loginLogin"><?= $tr('common.auth.login_label') ?></label>
      <input class="form-field__input" type="text" id="loginLogin" name="login"
             value="<?= $view->e($form['login'] ?? '') ?>"
             placeholder="<?= $tr('common.auth.login_placeholder') ?>" autocomplete="username" maxlength="254"
             aria-describedby="loginLoginError">
      <p class="form-field__error" id="loginLoginError" aria-live="polite"></p>
      <?php foreach (($errors['login'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field form-field--password">
      <label class="visually-hidden" for="loginPassword"><?= $tr('common.auth.password_label') ?></label>
      <input class="form-field__input" type="password" id="loginPassword" name="password"
             placeholder="<?= $tr('common.auth.password_placeholder') ?>" autocomplete="current-password" maxlength="64"
             aria-describedby="loginPasswordError">
      <button class="form-field__toggle" type="button"
              data-password-toggle="loginPassword"
              data-label-show="<?= $tr('common.auth.show_password') ?>"
              data-label-hide="<?= $tr('common.auth.hide_password') ?>"
              aria-label="<?= $tr('common.auth.show_password') ?>"
              title="<?= $tr('common.auth.show_password') ?>">
        <svg class="form-field__toggle-icon form-field__toggle-icon--show" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true" focusable="false">
          <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        <svg class="form-field__toggle-icon form-field__toggle-icon--hide" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true" focusable="false">
          <path d="M9.9 4.24A9.9 9.9 0 0 1 12 4c6.5 0 10 8 10 8a17.7 17.7 0 0 1-2.16 3.19"/>
          <path d="M6.61 6.61A17.7 17.7 0 0 0 2 12s3.5 8 10 8a9.9 9.9 0 0 0 5.39-1.61"/>
          <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
          <path d="m2 2 20 20"/>
        </svg>
      </button>
      <p class="form-field__error" id="loginPasswordError" aria-live="polite"></p>
      <?php foreach (($errors['password'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary" type="submit"><?= $tr('common.auth.sign_in') ?></button>

    <div class="login-form__links">
      <a class="link" href="<?= $view->e($withLang($view->url('password.email'))) ?>"><?= $tr('common.auth.forgot') ?></a>
      <a class="link" href="<?= $view->e($withLang($view->url('register'))) ?>"><?= $tr('common.auth.no_account') ?></a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/password-toggle.js"></script>
  <script src="/assets/js/login.js"></script>
<?php $view->endBlock('scripts'); ?>