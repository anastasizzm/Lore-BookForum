<?php $view->extends('auth'); ?>
<?php $tr = static fn(string $k, array $p = []): string => $view->e($view->t($k, $p)); ?>

<?php
$langQuery = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : null);

$withLang = static function (string $path) use ($langQuery): string {
    if ($langQuery === null || $langQuery === '') return $path;
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . urlencode($langQuery);
};
?>

<?php $view->startBlock('title'); ?><?= $tr('common.auth.newpass_tab') ?><?php $view->endBlock('title'); ?>

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
    <h1 class="login-card__title"><?= $tr('common.auth.newpass_title') ?></h1>
  </header>

  <p class="login-card__text">
    <?= $tr('common.auth.newpass_text') ?>
  </p>

  <?php
    $unboundErrors = [];
    foreach (($errors ?? []) as $field => $list) {
      if (in_array($field, ['password', 'password_confirm'], true)) continue;
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

  <form class="login-form" id="passwordResetForm"
        action="<?= $view->e($withLang($view->url('password.reset.submit'))) ?>"
        method="POST" novalidate>
    <?= $view->csrfField() ?>

    <input type="hidden" name="token" value="<?= $view->e($token ?? ($form['token'] ?? '')) ?>">

    <div class="form-field">
      <label class="visually-hidden" for="resetPassword"><?= $tr('common.auth.newpass_label') ?></label>
      <input class="form-field__input" type="password" id="resetPassword" name="password"
             placeholder="<?= $tr('common.auth.newpass_placeholder') ?>" autocomplete="new-password" maxlength="64"
             aria-describedby="resetPasswordError">
      <p class="form-field__error" id="resetPasswordError" aria-live="polite"></p>
      <?php foreach (($errors['password'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="resetPasswordConfirm"><?= $tr('common.auth.newpass_repeat_label') ?></label>
      <input class="form-field__input" type="password" id="resetPasswordConfirm" name="password_confirm"
             placeholder="<?= $tr('common.auth.newpass_repeat_placeholder') ?>" autocomplete="new-password" maxlength="64"
             aria-describedby="resetPasswordConfirmError">
      <p class="form-field__error" id="resetPasswordConfirmError" aria-live="polite"></p>
      <?php foreach (($errors['password_confirm'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary" type="submit"><?= $tr('common.auth.save_password') ?></button>

    <div class="login-form__links login-form__links--center">
      <a class="link" href="<?= $view->e($withLang($view->url('login'))) ?>"><?= $tr('common.auth.back_to_sign_in') ?></a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/password-reset.js"></script>
<?php $view->endBlock('scripts'); ?>