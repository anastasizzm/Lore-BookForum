<?php $view->extends('main'); ?>

<?php $view->startBlock('title'); ?>Change password - Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/profile.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php
$errors  = $errors  ?? [];
$changed = $changed ?? false;
?>

<?php $view->include('page-header', [
    'title'    => 'Change password',
    'subtitle' => 'Set a new password for your account. It must be at least 8 characters long.',
]); ?>

<div class="profile-edit-grid">
  <section class="profile-edit card-base">

    <?php if ($changed): ?>
      <div class="login-message" role="status">
        <p class="login-message__text">The password was successfully changed.</p>
      </div>
    <?php endif; ?>

    <?php
      // ошибки, у которых на форме нет своего поля (всё прочее с бэка)
      $unboundErrors = [];
      foreach ($errors as $field => $list) {
          if (in_array($field, ['current_password', 'password', 'password_confirm'], true)) continue;
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

    <h2 class="profile-edit__section-title">Password</h2>

    <!--
      Контракт с бэком:
        GET  /auth/change-password       (роут password.change,        AuthPolicy::Auth)
        POST /auth/change-password       (роут password.change.submit, AuthPolicy::Auth)
             поля: current_password, password, password_confirm, csrf-поле
             успех: редирект сюда же ?changed=1 (плашка сверху)
             ошибки: этот же шаблон с $errors[current_password|password|password_confirm]
      Оба роута — замыкания в config/routes.php (app/ не трогаем).
      Пары новых полей проверяет password-reset.js (общая логика со страницей
      «Set new password»: id формы и полей те же, LoreValidators + messages.js).
    -->
    <form action="<?= $view->url('password.change.submit') ?>" method="POST" id="passwordResetForm">
      <?= $view->csrfField() ?>

      <div class="form-field">
        <label for="changeCurrentPassword">Current password</label>
        <input class="form-field__input" type="password" id="changeCurrentPassword" name="current_password"
               placeholder="Enter current password" autocomplete="current-password" maxlength="64" required
               aria-describedby="currentPasswordError">
        <p class="form-field__error" id="currentPasswordError" aria-live="polite"><?php
          if (!empty($errors['current_password'])) echo $view->e($errors['current_password'][0]);
        ?></p>
      </div>

      <div class="form-field">
        <label for="resetPassword">New password</label>
        <input class="form-field__input" type="password" id="resetPassword" name="password"
               placeholder="Enter new password" autocomplete="new-password" maxlength="64"
               aria-describedby="resetPasswordError">
        <p class="form-field__error" id="resetPasswordError" aria-live="polite"></p>
        <?php foreach (($errors['password'] ?? []) as $err): ?>
          <p class="form-field__error"><?= $view->e($err) ?></p>
        <?php endforeach; ?>
      </div>

      <div class="form-field">
        <label for="resetPasswordConfirm">Confirm new password</label>
        <input class="form-field__input" type="password" id="resetPasswordConfirm" name="password_confirm"
               placeholder="Repeat new password" autocomplete="new-password" maxlength="64"
               aria-describedby="resetPasswordConfirmError">
        <p class="form-field__error" id="resetPasswordConfirmError" aria-live="polite"></p>
        <?php foreach (($errors['password_confirm'] ?? []) as $err): ?>
          <p class="form-field__error"><?= $view->e($err) ?></p>
        <?php endforeach; ?>
      </div>

      <button class="btn btn--primary" type="submit">Change password</button>
    </form>

  </section>
</div>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/password-reset.js"></script>
<?php $view->endBlock('scripts'); ?>
