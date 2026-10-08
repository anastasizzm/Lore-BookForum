<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'profile'); ?>

<?php $view->startBlock('title'); ?>Edit profile - Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/profile.css') ?>">
  <script src="/assets/js/profile-edit.js" defer></script>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php
$form   = $form   ?? [];
$errors = $errors ?? [];

$val = function (string $key, string $default = '') use ($form, $userData) {
    if (array_key_exists($key, $form) && $form[$key] !== null && $form[$key] !== '') {
        return (string)$form[$key];
    }
    return (string)($userData->$key ?? $default);
};

$currentAvatar = $val('avatar', 'default');

// Карта пресетов — один источник правды для пикера, avatar.php и sidebar.php
$avatarOptions = ['default' => null]
    + require __DIR__ . '/../../../partials/avatar-presets.php';

$initials = mb_strtoupper(
    mb_substr($userData->name    ?? '', 0, 1) .
    mb_substr($userData->surname ?? '', 0, 1)
);
if ($initials === '') {
    $initials = mb_strtoupper(mb_substr($userData->username ?? '', 0, 1));
}
?>

<?php $view->include('page-header', [
    'title'    => 'Edit profile',
    'subtitle' => 'Update your personal information.',
]); ?>

<section class="profile-edit card-base">

  <form action="/users/<?= (int)$userData->id ?>/edit"
        method="POST"
        id="profileEditForm" novalidate>
    <?= $view->csrfField() ?>
    <input type="hidden" name="_method" value="PUT">

    <h2 class="profile-edit__section-title">Avatar</h2>

    <div class="avatar-picker" role="radiogroup" aria-label="Choose an avatar">
      <?php foreach ($avatarOptions as $id => $preset): ?>
        <label class="avatar-picker__option" data-avatar="<?= $view->e($id) ?>">
          <input type="radio"
                 name="avatar"
                 value="<?= $view->e($id) ?>"
                 <?= $currentAvatar === $id ? 'checked' : '' ?>
                 class="avatar-picker__radio">
          <span class="avatar-picker__visual">
            <?php if ($preset === null): ?>
              <span class="avatar-picker__initials"><?= $view->e($initials) ?></span>
            <?php else: ?>
              <span class="avatar-picker__emoji"><?= $view->e($preset['icon']) ?></span>
            <?php endif; ?>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="form-field__error" data-error-for="avatar">
      <?php if (!empty($errors['avatar'])): ?>
        <span class="field-error-icon" title="<?= $view->e($errors['avatar'][0]) ?>">!</span>
      <?php endif; ?>
    </p>

    <h2 class="profile-edit__section-title">Profile information</h2>

    <div class="form-row--two-cols">

      <div class="form-field">
        <label for="profileName">Name</label>
        <input class="form-field__input"
               type="text"
               id="profileName"
               name="name"
               value="<?= $view->e($val('name')) ?>"
               autocomplete="given-name"
               maxlength="64"
               required>
        <p class="form-field__error" data-error-for="name">
          <?php if (!empty($errors['name'])): ?>
            <span class="field-error-icon" title="<?= $view->e($errors['name'][0]) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="form-field">
        <label for="profileSurname">Surname</label>
        <input class="form-field__input"
               type="text"
               id="profileSurname"
               name="surname"
               value="<?= $view->e($val('surname')) ?>"
               autocomplete="family-name"
               maxlength="64"
               required>
        <p class="form-field__error" data-error-for="surname">
          <?php if (!empty($errors['surname'])): ?>
            <span class="field-error-icon" title="<?= $view->e($errors['surname'][0]) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

    </div>

    <div class="form-field">
      <label for="profileUsername">Username</label>
      <input class="form-field__input"
             type="text"
             id="profileUsername"
             name="username"
             value="<?= $view->e($val('username')) ?>"
             autocomplete="username"
             minlength="3"
             maxlength="30"
             required>
      <p class="form-field__error" data-error-for="username">
        <?php if (!empty($errors['username'])): ?>
          <span class="field-error-icon" title="<?= $view->e($errors['username'][0]) ?>">!</span>
        <?php endif; ?>
      </p>
    </div>

    <div class="form-field">
      <label for="profileEmail">Email</label>
      <input class="form-field__input"
             type="email"
             id="profileEmail"
             name="email"
             value="<?= $view->e($val('email')) ?>"
             autocomplete="email"
             maxlength="255"
             required>
      <p class="form-field__error" data-error-for="email">
        <?php if (!empty($errors['email'])): ?>
          <span class="field-error-icon" title="<?= $view->e($errors['email'][0]) ?>">!</span>
        <?php endif; ?>
      </p>
    </div>

    <div class="form-field">
      <label for="profileBio">Biography</label>
      <textarea class="form-field__input form-field__input--textarea"
                id="profileBio"
                name="bio"
                rows="6"
                maxlength="500"
                placeholder="Tell readers about yourself..."><?= $view->e($val('bio')) ?></textarea>
      <p class="form-field__error" data-error-for="bio"></p>
    </div>

    <div class="profile-edit__actions">
      <a class="btn btn--secondary" href="/users/<?= (int)$userData->id ?>">Cancel</a>
      <button class="btn btn--primary" type="submit">Save changes</button>
    </div>
  </form>

</section>

<?php $view->endBlock('content'); ?>