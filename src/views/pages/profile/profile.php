<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'profile'); ?>

<?php $view->startBlock('title'); ?>
<?= $view->e(trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')) . ' — Profile') ?>
<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/profile.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php
// TODO: заменить на данные из контроллера
// Ожидаемые переменные: $user, $posts, $publications, $isOwner, $currentPage, $totalPages

$user = $user ?? [
    'name'     => 'Name',
    'surname'  => 'Surname',
    'username' => 'username',
    'avatar'   => null,
    'initials' => 'NS',
    'bio'      => '',
];

$posts        = $posts        ?? [];
$publications = $publications ?? [];

$isOwner     = $isOwner     ?? true;
$currentPage = $currentPage ?? 1;
$totalPages  = $totalPages  ?? 1;
?>

<div class="profile-layout">

  <div class="profile-layout__main">

    <!-- Profile header: avatar, name, @username, Edit -->
    <header class="profile-header card-base">
      <div class="profile-header__avatar">
        <?php $view->include('avatar', [
            'size'     => 'lg',
            'initials' => $user['initials'] ?? '',
            'src'      => $user['avatar']   ?? null,
        ]); ?>
      </div>

      <div class="profile-header__info">
        <h1 class="profile-header__name">
          <?= $view->e(trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''))) ?>
        </h1>
        <p class="profile-header__username">@<?= $view->e($user['username'] ?? '') ?></p>
      </div>

      <?php if ($isOwner): ?>
        <a class="btn-icon profile-header__edit"
           href="#"
           aria-label="Edit profile"
           title="Edit profile">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </a>
      <?php endif; ?>
    </header>

    <!-- Posts -->
    <section class="profile-posts">
      <h2 class="profile-posts__title">Posts</h2>

      <?php if (empty($posts)): ?>
        <p class="empty-state__text">No posts yet.</p>
      <?php else: ?>

        <div class="stack">
          <?php foreach ($posts as $post): ?>
            <article class="card-base profile-post">

              <?php if (!empty($post['title'])): ?>
                <h3 class="profile-post__title"><?= $view->e($post['title']) ?></h3>
              <?php endif; ?>

              <?php if (!empty($post['text'])): ?>
                <p class="profile-post__text"><?= $view->e($post['text']) ?></p>
              <?php endif; ?>

              <?php if (!empty($post['books'])): ?>
                <div class="profile-post__books">
                  <?php foreach ($post['books'] as $book): ?>
                    <a class="profile-post__book"
                       href="#">
                      <img src="<?= $view->e($book['cover'] ?? '') ?>" alt="">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="pagination" aria-label="Pagination">
            <?php if ($currentPage > 1): ?>
              <a class="pagination__btn"
                 href="?page=<?= (int)($currentPage - 1) ?>"
                 aria-label="Previous page">←</a>
            <?php else: ?>
              <span class="pagination__btn pagination__btn--disabled">←</span>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <?php if ($p === $currentPage): ?>
                <span class="pagination__btn pagination__btn--active"><?= $p ?></span>
              <?php else: ?>
                <a class="pagination__btn" href="?page=<?= $p ?>"><?= $p ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
              <a class="pagination__btn"
                 href="?page=<?= (int)($currentPage + 1) ?>"
                 aria-label="Next page">→</a>
            <?php else: ?>
              <span class="pagination__btn pagination__btn--disabled">→</span>
            <?php endif; ?>
          </nav>
        <?php endif; ?>

      <?php endif; ?>
    </section>

  </div>

  <aside class="profile-layout__sidebar">

    <!-- Biography -->
    <section class="card-base profile-sidebar-box">
      <h2 class="profile-sidebar-box__title">Biography</h2>
      <?php if (!empty($user['bio'])): ?>
        <p class="profile-sidebar-box__text"><?= nl2br($view->e($user['bio'])) ?></p>
      <?php else: ?>
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          No biography yet.
        </p>
      <?php endif; ?>
    </section>

    <!-- Publications -->
    <section class="card-base profile-sidebar-box">
      <h2 class="profile-sidebar-box__title">Publications</h2>

      <?php if (empty($publications)): ?>
        <p class="profile-sidebar-box__text profile-sidebar-box__text--muted">
          No publications yet.
        </p>
      <?php else: ?>
        <ul class="profile-publications">
          <?php foreach ($publications as $pub): ?>
            <li class="profile-publications__item">
              <a class="profile-publications__link"
                 href="<?= $view->url('book', ['id' => $pub['id'] ?? 0]) ?>">
                <span class="profile-publications__cover">
                  <img src="<?= $view->e($pub['cover'] ?? '') ?>" alt="">
                </span>
                <span class="profile-publications__title">
                  <?= $view->e($pub['title'] ?? '') ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

  </aside>

</div>

<?php $view->endBlock('content'); ?>