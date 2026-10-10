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

// Ключ аватара из БД ('cat', 'fox', 'default'...). Как его показать — решает partial avatar.php.
$avatarKey = (string) ($userData->avatar ?? '');

$isOwner = (int) ($user->id ?? 0) === (int) ($userData->id ?? 0);

// CSRF-токен для форм комментариев в карточках постов (карточки рендерит
// profile.js в JS — поле _token заполняется из data-posts-csrf)
$csrfToken = '';
if (preg_match('/value="([^"]*)"/', $view->csrfField(), $m)) {
    $csrfToken = html_entity_decode($m[1], ENT_QUOTES);
}

// Текущий пользователь — для карточек постов (те же data-cu-*, что в card-feed.php:
// их читает card-feed.js для оптимистичной вставки своих комментариев)
$cuInitials = mb_strtoupper(
    mb_substr($user?->name    ?? '', 0, 1) . mb_substr($user?->surname ?? '', 0, 1)
);
if ($cuInitials === '') {
    $cuInitials = mb_strtoupper(mb_substr($user?->username ?? '', 0, 1));
}
?>

<?php $view->startBlock('title'); ?><?= $view->e($displayName) ?> - Profile<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/profile.css">
  <!-- comments.js — общая структура комментариев (карточка, ответы, кнопки
       Show more/less); нужна card-feed.js (defer в main.php), который вешает
       комментарии под пост — карточки постов тут те же, что в ленте -->
  <script src="<?= $view->asset('js/comments.js') ?>"></script>
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
            'icon'   => $avatarKey,
        ]); ?>
      </div>

      <div class="profile-header__info">
        <h1 class="profile-header__name"><?= $view->e($displayName) ?></h1>
        <p class="profile-header__username">@<?= $view->e($userData->username ?? '') ?></p>
      </div>

      <?php if ($isOwner): ?>
        <!-- Роут users.profile.edit: /users/{userId}/profile/edit (раньше вёл на /users/{id}/edit -> 404) -->
        <a class="btn-icon profile-header__edit"
           href="<?= $view->url('users.profile.edit', ['userId' => (int)$userData->id]) ?>"
           aria-label="Edit profile"
           title="Edit profile">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </a>
      <?php endif; ?>
    </header>

    <!-- Посты — карточки ТАКИЕ ЖЕ, как в ленте (разметка card-feed.php):
         пост = корневой комментарий, под ним плоский список комментариев
         (без вложенности, как на book/article details). Грузит profile.js
         из GET /api/posts?creator={userId}&include=creator%2Bpublication -->
    <section class="profile-posts">
      <h2 class="profile-posts__title">Posts</h2>
      <div class="profile-posts__list"
           data-posts
           data-posts-user-id="<?= (int)($userData->id ?? 0) ?>"
           data-posts-author-username="<?= $view->e($userData->username ?? '') ?>"
           data-posts-cu-id="<?= (int)($user?->id ?? 0) ?>"
           data-posts-cu-name="<?= $view->e($user?->username ?? '') ?>"
           data-posts-cu-initials="<?= $view->e($cuInitials) ?>"
           data-posts-cu-avatar="<?= $view->e($user?->avatar ?? '') ?>"
           data-posts-csrf="<?= $view->e($csrfToken) ?>">
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          Loading...
        </p>
      </div>
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
           data-publications-more-url="<?= $view->e($view->url('users.profile.books', ['userId' => (int) $userData->id])) ?>">
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          Loading...
        </p>
      </div>
    </section>

  </aside>

</div>

<?php $view->endBlock('content'); ?>