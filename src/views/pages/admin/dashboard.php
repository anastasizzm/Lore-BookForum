<?php
/**
 * admin/dashboard — каркас админ-дашборда (сетка разделов).
 *
 * Статус: ЗАГЛОВКА без проверок прав — финальные имена разрешений
 * (canPublication, canManagePublication и т.п.) бэк ещё не назвал,
 * поэтому isAdmin / isRedactor здесь НЕ проверяем (см. задачу на сегодня).
 *
 * Роутов админки пока нет: config/routes.php и app/ для фронта заморожены.
 * Ссылки ниже ведут на dev-роут /pages/test (он рендерит любую вьюху),
 * чтобы каркас можно было посмотреть и прокликать.
 * TODO(роуты): заменить на $view->url('admin.dashboard'), url('admin.users'),
 * url('admin.books'), url('admin.articles'), url('admin.comments'),
 * url('admin.reports'), когда бэк добавит маршруты.
 *
 * Стили — public/assets/css/admin.css (.admin-grid, .admin-card).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: разделы дашборда. Счётчики придут с API админки ----
$cards = [
    ['href' => '/pages/test?page=admin/users',     'title' => $tr('common.admin.card_users'),     'hint' => $tr('common.admin.card_users_hint'),     'count' => '—'],
    ['href' => '/pages/test?page=admin/books',     'title' => $tr('common.admin.card_books'),     'hint' => $tr('common.admin.card_books_hint'),     'count' => '—'],
    ['href' => '/pages/test?page=admin/articles',  'title' => $tr('common.admin.card_articles'),  'hint' => $tr('common.admin.card_articles_hint'),  'count' => '—'],
    ['href' => '/pages/test?page=admin/comments',  'title' => $tr('common.admin.card_comments'),  'hint' => $tr('common.admin.card_comments_hint'),  'count' => '—'],
    // Раздела «Reports» сегодня нет (роута и вида) — карточка-заглушка:
    // рисуем div, а не ссылку с «#»: клик не должен ничего делать.
    ['href' => null, 'title' => $tr('common.admin.card_reports'), 'hint' => $tr('common.admin.card_reports_hint'), 'count' => '—', 'soon' => true],
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.dashboard_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.dashboard_title'),
    'subtitle' => $view->t('common.admin.dashboard_subtitle'),
]); ?>

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<div class="admin-grid">
  <?php foreach ($cards as $card): $soon = !empty($card['soon']); ?>
    <?php if ($soon): ?>
      <div class="admin-card admin-card--soon">
    <?php else: ?>
      <a class="admin-card" href="<?= $view->e((string) $card['href']) ?>">
    <?php endif; ?>

      <div class="admin-card__head">
        <h2 class="admin-card__title"><?= $card['title'] ?></h2>
        <span class="admin-card__count"><?= $view->e($card['count']) ?></span>
      </div>
      <p class="admin-card__hint"><?= $card['hint'] ?></p>

      <?php if ($soon): ?>
        <span class="admin-badge admin-badge--muted"><?= $tr('common.admin.coming_soon') ?></span>
      </div>
    <?php else: ?>
      </a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<?php $view->endBlock('content'); ?>
