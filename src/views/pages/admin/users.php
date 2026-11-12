<?php
/**
 * admin/users — таблица пользователей админки.
 *
 * Строки рисует public/assets/js/admin/features/users/list.js:
 *   GET /api/system/users?include=roles&page=N&ps=20
 * Роли приходят в том же ответе (include=roles), без N+1 запросов.
 * Серверного q у system users нет, поэтому поиск по Enter загружает
 * страницы system users и фильтрует их на клиенте (так видны роли,
 * e-mail, isBlocked и canManage). «Показать ещё» листает список дальше.
 *
 * Внутри строки — вложенный список ролей (раздел USER ROLES):
 * GET/POST/DELETE /api/system/users/{id}/roles.
 *
 * Кнопки Block/Unblock открывают ConfirmModal.confirm() и вызывают
 * POST/DELETE /api/users/{id}/block; строка обновляется по ответу API.
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

<?php $view->include('admin-back'); ?>

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

<?php
/*
 * Данные страницы уже есть у контроллера — отдаём их в разметку, чтобы первая
 * загрузка таблицы не делала запрос к API (JS читает блок один раз и удаляет;
 * поиск, фильтры и «Показать ещё» дальше идут через API как раньше).
 * Нет данных / не сериализуются — блок не выводится, JS сам сходит в API.
 */
$adminBoot = static function (string $name, mixed $data): void {
    if ($data === null || $data === []) return;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($json)) return;
    echo '<script type="application/json" data-admin-data="' . $name . '">' . $json . '</script>' . "\n";
};
// Вместе с первой страницей передаём meta.hasNext, чтобы «Показать ещё»
// не появлялось/не пропадало из-за эвристики по размеру страницы.
$usersBoot = $users ?? $items ?? null;
if (is_array($usersBoot) && isset($meta) && is_array($meta)) {
    $usersBoot = ['items' => $usersBoot, 'meta' => $meta];
}
$adminBoot('users', $usersBoot);
?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script type="module" src="<?= $view->asset('js/admin/main.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>