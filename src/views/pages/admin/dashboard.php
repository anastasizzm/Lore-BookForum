<?php
/**
 * admin/dashboard — админ-дашборд (сетка разделов).
 *
 * Ссылки карточек строятся по именам роутов из config/routes.php
 * (блок «Admin»): $view->url('admin.users') и т.д.
 *
 *   admin.users       → /admin/users
 *   admin.books       → /admin/books
 *   admin.articles    → /admin/articles
 *   admin.posts       → /admin/posts       (карточка «Комментарии»)
 *   admin.genres      → /admin/genres
 *   admin.categories  → /admin/categories
 *   admin.types       → /admin/types
 *   admin.roles       → /admin/roles
 *   admin.all-roles   → /admin/all-roles
 *
 * TODO(роуты): для «Жалоб» (admin/reports) роута в routes.php пока нет.
 * Когда бэк добавит 'admin.reports', вернуть карточку с 'route' => 'admin.reports'.
 *
 * Проверок прав здесь нет: доступ к каждой странице проверяет политика роута
 * (AuthPolicy::Admin / *Operation), дашборд только даёт ссылки.
 *
 * Стили — public/assets/css/admin.css (.admin-grid, .admin-card).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- Разделы дашборда. Счётчики придут с API админки ----
// 'route' — имя роута (url() генерирует адрес с учётом языка/префикса);
// 'href'  — запасной адрес, пока роута нет.
$cards = [
    ['route' => 'admin.users',      'title' => $tr('common.admin.card_users'),      'hint' => $tr('common.admin.card_users_hint'),      'count' => '—'],
    ['route' => 'admin.books',      'title' => $tr('common.admin.card_books'),      'hint' => $tr('common.admin.card_books_hint'),      'count' => '—'],
    ['route' => 'admin.articles',   'title' => $tr('common.admin.card_articles'),   'hint' => $tr('common.admin.card_articles_hint'),   'count' => '—'],
    // «Комментарии» в админке — это посты: роут admin.posts
    ['route' => 'admin.posts',      'title' => $tr('common.admin.card_comments'),   'hint' => $tr('common.admin.card_comments_hint'),   'count' => '—'],
    // Справочники (Genres / Categories / Types)
    ['route' => 'admin.genres',     'title' => $tr('common.admin.card_genres'),     'hint' => $tr('common.admin.card_genres_hint'),     'count' => '—'],
    ['route' => 'admin.categories', 'title' => $tr('common.admin.card_categories'), 'hint' => $tr('common.admin.card_categories_hint'), 'count' => '—'],
    ['route' => 'admin.types',      'title' => $tr('common.admin.card_types'),      'hint' => $tr('common.admin.card_types_hint'),      'count' => '—'],
    // «Жалобы» скрыты, пока нет раздела: вернуть карточку admin.reports, когда появится роут

    // Системные роли → admin/roles.php
    ['route' => 'admin.roles',
     'title' => $tr('common.admin.roles_title'),          // "Системные роли"
     'hint'  => $tr('common.admin.roles_subtitle'),       // "Права ролей на всём форуме"
     'count' => '—'],

    // Все роли → admin/all-roles.php
    ['route' => 'admin.all-roles',
     'title' => $tr('common.admin.all_roles_card_title'), // "Все роли"
     'hint'  => $tr('common.admin.all_roles_card_text'),  // "Названия ролей и их видимость"
     'count' => '—'],
];

// Права: контроллер передаёт $allowed — [имя роута => bool] (AdminPanelController::dashboardAccess).
// Карточка показывается, только если у пользователя есть доступ к разделу.
// Нет карты $allowed (страницу отрисовали без контроллера) — показываем все карточки.
$allowed = $allowed ?? null;
$cards = array_values(array_filter($cards, static function (array $card) use ($allowed): bool {
    if (!is_array($allowed) || empty($card['route'])) return true;
    return !empty($allowed[$card['route']]);
}));

/** Адрес карточки: по имени роута, иначе запасной href. */
$cardHref = static function (array $card) use ($view): string {
    if (!empty($card['route'])) return (string) $view->url($card['route']);
    return (string) ($card['href'] ?? '#');
};
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

<?php if (!$cards): ?>
  <p class="admin-note"><?= $view->e($view->t('common.admin.dashboard_empty') === 'common.admin.dashboard_empty'
      ? 'No sections are available for your account'
      : $view->t('common.admin.dashboard_empty')) ?></p>
<?php endif; ?>

<div class="admin-grid">
  <?php foreach ($cards as $card): $soon = !empty($card['soon']); ?>
    <?php if ($soon): ?>
      <div class="admin-card admin-card--soon">
    <?php else: ?>
      <a class="admin-card" href="<?= $view->e($cardHref($card)) ?>">
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