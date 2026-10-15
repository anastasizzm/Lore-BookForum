<?php
/**
 * admin/users — каркас таблицы пользователей админки.
 *
 * Статус: ЗАГЛОВКА без проверок прав — финальные имена разрешений
 * (canPublication, canManagePublication и т.п.) бэк ещё не назвал,
 * поэтому isAdmin / isRedactor здесь НЕ проверяем.
 * Реальных запросов к API админки тоже нет: API ещё не вынесли.
 *
 * Роли/статусы — заглушки; когда бэк назовёт поля, заменить
 * $role / $status на реальные значения из ответа.
 *
 * Кнопка Block открывает ConfirmModal.confirm() (см. public/assets/js/admin.js),
 * TODO(fetch) внутри — PATCH /api/admin/users/{id}.
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-toolbar,
 * .admin-badge, .admin-actions).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: строки таблицы. Придут из GET /api/admin/users ----
$users = [
    ['id' => 1, 'username' => 'anastasizzm', 'email' => 'anastasia@example.com', 'role' => 'admin', 'status' => 'active'],
    ['id' => 2, 'username' => 'reader_one',  'email' => 'reader1@example.com',   'role' => 'user',  'status' => 'active'],
    ['id' => 3, 'username' => 'spam_bot',    'email' => 'spam@example.com',      'role' => 'user',  'status' => 'blocked'],
];
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

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         placeholder="<?= $tr('common.admin.search_users_ph') ?>"
         aria-label="<?= $tr('common.admin.search_users_ph') ?>"
         autocomplete="off">
  <span class="admin-toolbar__count"><?= count($users) ?></span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="users">
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_username') ?></th>
        <th scope="col"><?= $tr('common.admin.col_email') ?></th>
        <th scope="col"><?= $tr('common.admin.col_role') ?></th>
        <th scope="col"><?= $tr('common.admin.col_status') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $userRow): $userRow['id'] = (int) $userRow['id']; ?>
        <tr data-user-id="<?= $userRow['id'] ?>">
          <td class="admin-table__id"><?= $userRow['id'] ?></td>
          <td><?= $view->e($userRow['username']) ?></td>
          <td><span class="admin-table__clip" title="<?= $view->e($userRow['email']) ?>"><?= $view->e($userRow['email']) ?></span></td>
          <td>
            <span class="admin-badge <?= $userRow['role'] === 'admin' ? 'admin-badge--accent' : '' ?>">
              <?= $userRow['role'] === 'admin' ? $tr('common.admin.role_admin') : $tr('common.admin.role_user') ?>
            </span>
          </td>
          <td>
            <?php if ($userRow['status'] === 'blocked'): ?>
              <span class="admin-badge admin-badge--danger"><?= $tr('common.admin.status_blocked') ?></span>
            <?php else: ?>
              <span class="admin-badge admin-badge--success"><?= $tr('common.admin.status_active') ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div class="admin-actions">
              <!-- TODO(роуты): url('users.profile', ['userId' => $userRow['id']]) -->
              <a class="btn btn--secondary" href="/pages/test?page=admin/users"><?= $tr('common.admin.action_view') ?></a>

              <!-- ConfirmModal.confirm() — см. public/assets/js/admin.js.
                   TODO(api): fetch PATCH /api/admin/users/{id} (роута ещё нет). -->
              <button type="button"
                      class="btn btn--secondary"
                      data-admin-block
                      data-user-id="<?= $userRow['id'] ?>"
                      data-user-name="<?= $view->e($userRow['username']) ?>">
                <?= $tr('common.admin.action_block') ?>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$users): ?>
        <tr class="admin-table__empty">
          <td colspan="6"><?= $tr('common.admin.empty') ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
