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

<?php $view->startBlock('title'); ?><?= $tr('common.auth.register_title') ?><?php $view->endBlock('title'); ?>

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
    <h1 class="login-card__title"><?= $tr('common.auth.register_title') ?></h1>
  </header>

  <form class="login-form" id="registerForm"
        action="<?= $view->e($withLang($view->url('register'))) ?>"
        method="POST" novalidate>
    <?= $view->csrfField() ?>

    <div class="form-field">
      <label class="visually-hidden" for="registerEmail"><?= $tr('common.auth.email_label') ?></label>
      <input class="form-field__input" type="email" id="registerEmail" name="email"
             value="<?= $view->e($form['email'] ?? '') ?>"
             placeholder="<?= $tr('common.auth.email_placeholder') ?>" autocomplete="email" maxlength="254"
             aria-describedby="registerEmailError">
      <p class="form-field__error" id="registerEmailError" aria-live="polite"></p>
      <?php foreach (($errors['email'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerUsername"><?= $tr('common.auth.username_label') ?></label>
      <input class="form-field__input" type="text" id="registerUsername" name="username"
             value="<?= $view->e($form['username'] ?? '') ?>"
             placeholder="<?= $tr('common.auth.username_placeholder') ?>" autocomplete="username" maxlength="32"
             aria-describedby="registerUsernameError">
      <p class="form-field__error" id="registerUsernameError" aria-live="polite"></p>
      <?php foreach (($errors['username'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerName"><?= $tr('common.auth.name_label') ?></label>
      <input class="form-field__input" type="text" id="registerName" name="name"
             value="<?= $view->e($form['name'] ?? '') ?>"
             placeholder="<?= $tr('common.auth.name_placeholder') ?>" autocomplete="given-name" maxlength="64"
             aria-describedby="registerNameError">
      <p class="form-field__error" id="registerNameError" aria-live="polite"></p>
      <?php foreach (($errors['name'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerSurname"><?= $tr('common.auth.surname_label') ?></label>
      <input class="form-field__input" type="text" id="registerSurname" name="surname"
             value="<?= $view->e($form['surname'] ?? '') ?>"
             placeholder="<?= $tr('common.auth.surname_placeholder') ?>" autocomplete="family-name" maxlength="64"
             aria-describedby="registerSurnameError">
      <p class="form-field__error" id="registerSurnameError" aria-live="polite"></p>
      <?php foreach (($errors['surname'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerPassword"><?= $tr('common.auth.password_label') ?></label>
      <input class="form-field__input" type="password" id="registerPassword" name="password"
             placeholder="<?= $tr('common.auth.password_placeholder') ?>" autocomplete="new-password" maxlength="64"
             aria-describedby="registerPasswordError">
      <p class="form-field__error" id="registerPasswordError" aria-live="polite"></p>
      <?php foreach (($errors['password'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="form-field">
      <label class="visually-hidden" for="registerPasswordConfirm"><?= $tr('common.auth.password_repeat_label') ?></label>
      <input class="form-field__input" type="password" id="registerPasswordConfirm" name="password_confirm"
             placeholder="<?= $tr('common.auth.password_repeat_placeholder') ?>" autocomplete="new-password" maxlength="64"
             aria-describedby="registerPasswordConfirmError">
      <p class="form-field__error" id="registerPasswordConfirmError" aria-live="polite"></p>
      <?php foreach (($errors['password_confirm'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary" type="submit"><?= $tr('common.auth.sign_up') ?></button>

    <div class="login-form__links login-form__links--center">
      <a class="link" href="<?= $view->e($withLang($view->url('login'))) ?>"><?= $tr('common.auth.have_account') ?></a>
    </div>
  </form>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/register.js"></script>
<?php $view->endBlock('scripts'); ?>