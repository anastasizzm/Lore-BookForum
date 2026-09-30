<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'profile'); ?>

<?php $view->startBlock('title'); ?>Edit profile — Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/profile.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php
$user = $user ?? [
    'name'     => '',
    'surname'  => '',
    'username' => '',
    'bio'      => '',
];

$form   = $form   ?? [];
$errors = $errors ?? [];
?>

<?php $view->include('page-header', [
    'title'    => 'Edit profile',
    'subtitle' => 'Update your personal information.',
]); ?>

<section class="profile-edit card-base">

  <h2 class="profile-edit__section-title">Profile information</h2>

  <form action="#" method="POST" novalidate>
    <?= $view->csrfField() ?>

    <div class="form-row--two-cols">

      <div class="form-field">
        <label for="profileName">Name</label>
        <input class="form-field__input"
               type="text"
               id="profileName"
               name="name"
               value="<?= $view->e($form['name'] ?? $user['name'] ?? '') ?>"
               autocomplete="given-name"
               maxlength="64"
               required>
        <?php foreach (($errors['name'] ?? []) as $err): ?>
          <p class="form-field__error"><?= $view->e($err) ?></p>
        <?php endforeach; ?>
      </div>

      <div class="form-field">
        <label for="profileSurname">Surname</label>
        <input class="form-field__input"
               type="text"
               id="profileSurname"
               name="surname"
               value="<?= $view->e($form['surname'] ?? $user['surname'] ?? '') ?>"
               autocomplete="family-name"
               maxlength="64"
               required>
        <?php foreach (($errors['surname'] ?? []) as $err): ?>
          <p class="form-field__error"><?= $view->e($err) ?></p>
        <?php endforeach; ?>
      </div>

    </div>

    <div class="form-field">
      <label for="profileBio">Biography</label>
      <textarea class="form-field__input form-field__input--textarea"
                id="profileBio"
                name="bio"
                rows="6"
                maxlength="500"
                placeholder="Tell readers about yourself..."><?= $view->e($form['bio'] ?? $user['bio'] ?? '') ?></textarea>
      <?php foreach (($errors['bio'] ?? []) as $err): ?>
        <p class="form-field__error"><?= $view->e($err) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="profile-edit__actions">
      <a class="btn btn--secondary" href="#">Cancel</a>
      <button class="btn btn--primary" type="submit">Save changes</button>
    </div>
  </form>

</section>

<?php $view->endBlock('content'); ?>