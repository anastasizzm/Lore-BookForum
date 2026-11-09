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
 * ПРАВА: контроллер не передаёт $allowed (BACKEND-FIXES B-17), поэтому
 * карточки скрываются на клиенте через permissions.js по реальным правилам
 * GET /api/system/rules. 'requires' — ключ из public/assets/js/permissions.js.
 * Если правила не загрузились, карточка остаётся скрытой (fail-closed).
 *
 * СЧЁТЧИКИ: API админки их не отдаёт (BACKEND-FIXES B-17), поэтому цифры
 * не показываем вовсе — вместо выдуманных «—».
 *
 * Стили — public/assets/css/admin.css (.admin-grid, .admin-card).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- Разделы дашборда. Счётчики API не отдаёт — цифры не рисуем. ----
// 'route'    — имя роута (url() генерирует адрес с учётом языка/префикса);
// 'requires' — ключ permissions.js, по которому карточку показывать;
// 'href'     — запасной адрес, пока роута нет.
$cards = [
    ['route' => 'admin.users',      'requires' => 'system.managePeople',      'title' => $tr('common.admin.card_users'),      'hint' => $tr('common.admin.card_users_hint')],
    ['route' => 'admin.books',      'requires' => 'publication.control',      'title' => $tr('common.admin.card_books'),      'hint' => $tr('common.admin.card_books_hint')],
    ['route' => 'admin.articles',   'requires' => 'publication.control',      'title' => $tr('common.admin.card_articles'),   'hint' => $tr('common.admin.card_articles_hint')],
    // «Комментарии» в админке — это посты: роут admin.posts
    ['route' => 'admin.posts',      'requires' => 'post.control',             'title' => $tr('common.admin.card_comments'),   'hint' => $tr('common.admin.card_comments_hint')],
    // Справочники (Genres / Categories / Types)
    ['route' => 'admin.genres',     'requires' => 'system.manageAdditionals', 'title' => $tr('common.admin.card_genres'),     'hint' => $tr('common.admin.card_genres_hint')],
    ['route' => 'admin.categories', 'requires' => 'system.manageAdditionals', 'title' => $tr('common.admin.card_categories'), 'hint' => $tr('common.admin.card_categories_hint')],
    ['route' => 'admin.types',      'requires' => 'system.manageAdditionals', 'title' => $tr('common.admin.card_types'),      'hint' => $tr('common.admin.card_types_hint')],
    // «Жалобы» скрыты, пока нет раздела и API: вернуть карточку admin.reports, когда появится роут

    // Системные роли → admin/roles.php
    ['route' => 'admin.roles',
     'requires' => 'system.manageRoles',
     'title' => $tr('common.admin.roles_title'),          // "Системные роли"
     'hint'  => $tr('common.admin.roles_subtitle')],      // "Права ролей на всём форуме"

    // Все роли → admin/all-roles.php
    ['route' => 'admin.all-roles',
     'requires' => 'system.manageRoles',
     'title' => $tr('common.admin.all_roles_card_title'), // "Все роли"
     'hint'  => $tr('common.admin.all_roles_card_text')], // "Названия ролей и их видимость"
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

<?php if (!$cards): ?>
  <p class="admin-note"><?= $view->e($view->t('common.admin.dashboard_empty') === 'common.admin.dashboard_empty'
      ? 'No sections are available for your account'
      : $view->t('common.admin.dashboard_empty')) ?></p>
<?php endif; ?>

<div class="admin-grid">
  <?php foreach ($cards as $card): $soon = !empty($card['soon']); ?>
    <?php
      $requires     = !empty($card['requires']) ? (string) $card['requires'] : '';
      $requiresKind = str_starts_with($requires, 'publication.') ? 'book' : '';
    ?>
    <?php if ($soon): ?>
      <div class="admin-card admin-card--soon"
           data-admin-card
           <?= $requires !== '' ? 'data-requires="' . $view->e($requires) . '"' : '' ?>
           <?= $requiresKind !== '' ? 'data-requires-kind="' . $view->e($requiresKind) . '"' : '' ?>
           hidden>
    <?php else: ?>
      <a class="admin-card"
         data-admin-card
         href="<?= $view->e($cardHref($card)) ?>"
         <?= $requires !== '' ? 'data-requires="' . $view->e($requires) . '"' : '' ?>
         <?= $requiresKind !== '' ? 'data-requires-kind="' . $view->e($requiresKind) . '"' : '' ?>
         hidden>
    <?php endif; ?>

      <div class="admin-card__head">
        <h2 class="admin-card__title"><?= $card['title'] ?></h2>
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

<p class="admin-note" data-admin-dashboard-empty hidden>
  <?= $view->e($view->t('common.admin.dashboard_empty') === 'common.admin.dashboard_empty'
      ? 'No sections are available for your account'
      : $view->t('common.admin.dashboard_empty')) ?>
</p>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="<?= $view->asset('js/admin/dashboard.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>