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

<?php $view->startBlock('title'); ?><?= $tr('common.auth.forgot_tab') ?><?php $view->endBlock('title'); ?>

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
    <h1 class="login-card__title"><?= $tr('common.auth.forgot_title') ?></h1>
  </header>

  <p class="login-card__text">
    <?= $tr('common.auth.forgot_text') ?>
  </p>

  <?php
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

  <form class="login-form" id="passwordEmailForm"
        action="<?= $view->e($withLang($view->url('password.email.submit'))) ?>"
        method="POST" novalidate>
    <?= $view->csrfField() ?>

    <div class="form-field">
      <label class="visually-hidden" for="passwordEmail"><?= $tr('common.auth.email_label') ?></label>
      <input class="form-field__input" type="email" id="passwordEmail" name="email"
             value="<?= $view->e($form['email'] ?? '') ?>"
             placeholder="<?= $tr('common.auth.email_placeholder') ?>" autocomplete="email" maxlength="254"
             aria-describedby="passwordEmailError">
      <p class="form-field__error" id="passwordEmailError" aria-live="polite"></p>
      <?php foreach (($errors['email'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary" type="submit"><?= $tr('common.auth.send_link') ?></button>

    <div class="login-form__links login-form__links--center">
      <a class="link" href="<?= $view->e($withLang($view->url('login'))) ?>"><?= $tr('common.auth.back_to_sign_in') ?></a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/password-email.js"></script>
<?php $view->endBlock('scripts'); ?>