<?php
/**
 * admin/roles — страница системных ролей админки (список + окна прав и создания).
 *
 * Данных на странице нет: здесь только каркас таблицы. Строки рисует
 * public/assets/js/admin.js (раздел ROLES) по ответу GET /api/system/roles —
 * там же считается число прав и подбирается форма слова («1 правило / 2 правила / 5 правил»).
 * Пока список грузится, под шапкой показывается статус (data-roles-status),
 * при ошибке — её текст.
 *
 * КОНТРАКТ:
 *   GET  /api/system/roles          -> { items: [ { role: {id,title,color,createdAt},
 *                                                  systemPermissions: {...},
 *                                                  postPermissions: {...},
 *                                                  pubPermissions: {...},
 *                                                  canManage, version } ] }
 *   POST /api/system/roles          -> создание (тело: translations, visibility, color;
 *                                      visibility по умолчанию 'public')
 *   GET  /api/system/roles?q=…      -> серверный поиск (только по Enter)
 *   PUT  /api/system/roles/{roleId} -> 200, заголовок ETag: "newVersion"
 *          headers: If-Match: "version"
 *          body: { systemPermissions: {...}, postPermissions: {...},
 *                  pubPermissions: {...} }
 *   DELETE /api/system/roles/{roleId} -> удаление, headers: If-Match: "version"
 *
 * Группы прав (ключи бэка — см. SystemAccessRules / PostAccessRules /
 * PublicationAccessRules::toArray):
 *   system: manageAccounts, manageAdditionals, managePeople, manageRoles
 *   posts:  create, update, delete
 *   pubs:   create, updateMeta, updateContent, delete, read,
 *           managePeople, manageRoles
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-badge,
 * .admin-role__*), каркас .modal — modal.css.
 * Логика — public/assets/js/admin.js (раздел ROLES).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.roles_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.roles_title'),
    'subtitle' => $view->t('common.admin.roles_subtitle'),
]); ?>

<!-- Загрузка списка / ошибка GET /api/system/roles (заполняет admin.js) -->
<p class="admin-toolbar__status" data-roles-status role="status" aria-live="polite"><?= $tr('common.admin.roles_loading') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-admin-search
         placeholder="<?= $tr('common.admin.search_roles_ph') ?>"
         aria-label="<?= $tr('common.admin.search_roles_ph') ?>"
         enterkeyhint="search"
         autocomplete="off">
  <button type="button"
          class="btn btn--primary admin-toolbar__add admin-toolbar__add--icon"
          data-rolenew-open
          title="<?= $tr('common.admin.role_new_title') ?>"
          aria-label="<?= $tr('common.admin.role_new_title') ?>">+</button>
  <span class="admin-toolbar__count" data-admin-count>0</span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="roles" data-roles-table>
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_role') ?></th>
        <th scope="col"><?= $tr('common.admin.col_permissions') ?></th>
        <th scope="col"><?= $tr('common.admin.col_delete') ?></th>
      </tr>
    </thead>
    <!-- Строки добавляет admin.js после GET /api/system/roles; здесь — только строка «пусто» -->
    <tbody data-roles-rows>
      <tr class="admin-table__empty" data-admin-empty hidden>
        <td colspan="4"><?= $tr('common.admin.empty') ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Модалка прав роли: три группы чекбоксов (system / posts / pubs) -->
<?php $view->include('admin-role-modal'); ?>

<!-- Окно «Новая роль»: название, языки, Private/Public (по умолчанию Public), цвет, Save / Permissions -->
<?php $view->include('admin-role-create-modal'); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>