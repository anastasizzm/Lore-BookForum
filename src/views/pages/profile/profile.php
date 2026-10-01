<?php $view->extends('main'); ?>

<?php
$view->setBlock('selectedTab', 'profile');

$displayName = trim(($userData->name ?? '') . ' ' . ($userData->surname ?? ''));
if ($displayName === '') $displayName = $userData->username;

$initials = mb_strtoupper(
    mb_substr($userData->name    ?? '', 0, 1) .
    mb_substr($userData->surname ?? '', 0, 1)
);
if ($initials === '') {
    $initials = mb_strtoupper(mb_substr($userData->username ?? '', 0, 1));
}

$avatarRaw = $userData->avatar ?? '';
$avatarSrc = ($avatarRaw !== '' && $avatarRaw !== 'default')
    ? '/uploads/avatars/' . $avatarRaw
    : null;

$isOwner = ($user->id ?? 0) === ($userData->id ?? 0);
?>

<?php $view->startBlock('title'); ?><?= $view->e($displayName) ?> - Profile<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/profile.css">
  <script src="/assets/js/profile.js" defer></script>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<div class="profile-layout">

  <div class="profile-layout__main">

    <header class="profile-header card-base">
      <div class="profile-header__avatar">
        <?php $view->include('avatar', [
            'size'     => 'lg',
            'initials' => $initials,
            'src'      => $avatarSrc,
        ]); ?>
      </div>

      <div class="profile-header__info">
        <h1 class="profile-header__name"><?= $view->e($displayName) ?></h1>
        <p class="profile-header__username">@<?= $view->e($userData->username ?? '') ?></p>
      </div>

      <?php if ($isOwner): ?>
        <a class="btn-icon profile-header__edit"
           href="/users/<?= (int)$userData->id ?>/edit"
           aria-label="Edit profile"
           title="Edit profile">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </a>
      <?php endif; ?>
    </header>

    <section class="profile-posts">
      <h2 class="profile-posts__title">Posts</h2>
      <p class="empty-state__text">No posts yet.</p>
    </section>

  </div>

  <aside class="profile-layout__sidebar">

    <section class="card-base profile-sidebar-box">
      <h2 class="profile-sidebar-box__title">Biography</h2>
      <?php if (!empty($userData->bio)): ?>
        <p class="profile-sidebar-box__text"><?= nl2br($view->e($userData->bio)) ?></p>
      <?php else: ?>
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          No biography yet.
        </p>
      <?php endif; ?>
    </section>

    <section class="card-base profile-sidebar-box">
      <h2 class="profile-sidebar-box__title">Publications</h2>
      <div class="profile-publications"
           data-publications
           data-publications-user-id="<?= (int)$userData->id ?>"
           data-publications-limit="5"
           data-publications-more-url="/users/<?= (int)$userData->id ?>/books">
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          Loading...
        </p>
      </div>
    </section>

  </aside>

</div>

<?php $view->endBlock('content'); ?>