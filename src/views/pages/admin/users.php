<?php
/**
 * admin/users — таблица пользователей админки.
 *
 * Строки рисует public/assets/js/admin.js (раздел USERS LIST):
 *   GET /api/users?q=…&page=N&ps=20
 * Поиск уходит в API только по Enter (и при очистке поля крестиком),
 * «Показать ещё» подгружает следующую страницу.
 *
 * Внутри строки — вложенный список ролей (раздел USER ROLES):
 * GET/POST /api/system/users/{id}/roles.
 *
 * Кнопка Block открывает ConfirmModal.confirm(); роута блокировки в
 * endpoints.php пока нет, поэтому блок применяется локально.
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-toolbar,
 * .admin-badge, .admin-actions, .admin-user-roles).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.users_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.users_title'),
    'subtitle' => $view->t('common.admin.users_subtitle'),
]); ?>

<p class="admin-toolbar__status" data-users-status role="status" aria-live="polite"><?= $tx('common.admin.users_loading', 'Loading users…') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-users-search
         placeholder="<?= $tr('common.admin.search_users_ph') ?>"
         aria-label="<?= $tr('common.admin.search_users_ph') ?>"
         enterkeyhint="search"
         autocomplete="off">
  <span class="admin-toolbar__count" data-users-count>0</span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="users" data-users-table>
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_username') ?></th>
        <th scope="col"><?= $tr('common.admin.col_email') ?></th>
        <th scope="col"><?= $tr('common.admin.col_role') ?></th>
        <th scope="col"><?= $tr('common.admin.col_status') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
        <th scope="col" class="admin-table__expand" aria-label="<?= $tx('common.admin.user_roles_toggle', 'Roles') ?>"></th>
      </tr>
    </thead>
    <tbody data-users-rows></tbody>
  </table>
</div>

<div class="admin-users__more">
  <button type="button" class="btn btn--secondary" data-users-more hidden>
    <?= $tx('common.admin.users_load_more', 'Show more') ?>
  </button>
</div>

<!-- Модалка «Добавить роль пользователю» -->
<?php $view->include('admin-user-role-modal'); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>