<?php $view->extends('main'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
$view->setBlock('selectedTab', 'profile');

// Сохраняем текущую локаль во всех ссылках — бэк читает язык из ?lang=,
// иначе переход на другую страницу переключит его на Accept-Language.
$langQuery = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : null);

/** Обёртка над $view->url(): добавляет ?lang=<текущая> ко всем ссылкам. */
$url = static function (string $name, array $params = []) use ($view, $langQuery): string {
    $base = $view->url($name, $params);
    if ($langQuery === null || $langQuery === '') return $base;
    return $base . (str_contains($base, '?') ? '&' : '?') . 'lang=' . urlencode($langQuery);
};

/** Для произвольного пути (например, /users/{id}/edit) — та же логика. */
$withLang = static function (string $path) use ($langQuery): string {
    if ($langQuery === null || $langQuery === '') return $path;
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . urlencode($langQuery);
};

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
?>

<?php $view->startBlock('title'); ?><?= $tr('common.profile.tab_title', ['name' => $displayName]) ?><?php $view->endBlock('title'); ?>

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
            'icon'     => $avatarKey,
        ]); ?>
      </div>

      <div class="profile-header__info">
        <h1 class="profile-header__name"><?= $view->e($displayName) ?></h1>
        <p class="profile-header__username">@<?= $view->e($userData->username ?? '') ?></p>
      </div>

      <?php if ($isOwner): ?>
        <a class="btn-icon profile-header__edit"
           href="<?= $view->e($withLang('/users/' . (int)$userData->id . '/edit')) ?>"
           aria-label="<?= $tr('common.profile.edit') ?>"
           title="<?= $tr('common.profile.edit') ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </a>
      <?php endif; ?>
    </header>

    <section class="profile-posts">
      <h2 class="profile-posts__title"><?= $tr('common.profile.posts') ?></h2>
      <p class="empty-state__text"><?= $tr('common.profile.no_posts') ?></p>
    </section>

  </div>

  <aside class="profile-layout__sidebar">

    <section class="card-base profile-sidebar-box">
      <h2 class="profile-sidebar-box__title"><?= $tr('common.profile.bio') ?></h2>
      <?php if (!empty($userData->bio)): ?>
        <p class="profile-sidebar-box__text"><?= nl2br($view->e($userData->bio)) ?></p>
      <?php else: ?>
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          <?= $tr('common.profile.no_bio') ?>
        </p>
      <?php endif; ?>
    </section>

    <section class="card-base profile-sidebar-box">
      <h2 class="profile-sidebar-box__title"><?= $tr('common.profile.publications') ?></h2>
      <div class="profile-publications"
           data-publications
           data-publications-user-id="<?= (int)$userData->id ?>"
           data-publications-limit="5"
           data-publications-more-url="<?= $view->e($url('users.profile.books', ['userId' => (int) $userData->id])) ?>">
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          <?= $tr('common.common.loading') ?>
        </p>
      </div>
    </section>

  </aside>

</div>

<?php $view->endBlock('content'); ?>