<?php
/**
 * admin/roles — страница системных ролей админки (список + модалка прав).
 *
 * Статус: UI на моках. Строки рендерит сервер из мок-массива ниже, но у
 * каждой роли в JSON (data-roles-data) лежит полная копия — из неё admin.js
 * (раздел ROLES) наполняет модалку прав и применяет изменения локально.
 *
 * КОНТРАКТ, к которому готовимся:
 *   GET  /api/system/roles          -> { items: [ { role: {id,title,color,createdAt},
 *                                                  systemPermissions: {...},
 *                                                  postPermissions: {...},
 *                                                  pubPermissions: {...},
 *                                                  canManage, version } ] }
 *   PUT  /api/system/roles/{roleId} -> 200, заголовок ETag: "newVersion"
 *          headers: If-Match: "version"
 *          body: { systemPermissions: {...}, postPermissions: {...},
 *                  pubPermissions: {...} }
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

// ---- ЗАГЛУШКА: список ролей. Придут из GET /api/system/roles ----
$roles = [
    [
        'role' => ['id' => 0, 'title' => 'Administrator', 'color' => '#D64545', 'createdAt' => '2026-01-01'],
        'systemPermissions' => ['manageAccounts' => true, 'manageAdditionals' => true, 'managePeople' => true, 'manageRoles' => true],
        'postPermissions'   => ['create' => true, 'update' => true, 'delete' => true],
        'pubPermissions'    => ['create' => true, 'updateMeta' => true, 'updateContent' => true, 'delete' => true, 'read' => true, 'managePeople' => true, 'manageRoles' => true],
        'canManage' => true, 'version' => 1,
    ],
    [
        'role' => ['id' => 1, 'title' => 'Moderator', 'color' => '#1C7ED6', 'createdAt' => '2026-01-01'],
        'systemPermissions' => ['manageAccounts' => false, 'manageAdditionals' => false, 'managePeople' => true, 'manageRoles' => false],
        'postPermissions'   => ['create' => true, 'update' => true, 'delete' => true],
        'pubPermissions'    => ['create' => true, 'updateMeta' => true, 'updateContent' => true, 'delete' => false, 'read' => true, 'managePeople' => true, 'manageRoles' => false],
        'canManage' => true, 'version' => 1,
    ],
    [
        'role' => ['id' => 2, 'title' => 'Curator', 'color' => '#2F9E44', 'createdAt' => '2026-02-10'],
        'systemPermissions' => ['manageAccounts' => false, 'manageAdditionals' => true, 'managePeople' => false, 'manageRoles' => false],
        'postPermissions'   => ['create' => true, 'update' => false, 'delete' => false],
        'pubPermissions'    => ['create' => true, 'updateMeta' => false, 'updateContent' => false, 'delete' => false, 'read' => true, 'managePeople' => false, 'manageRoles' => false],
        'canManage' => true, 'version' => 1,
    ],
];
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

<p class="admin-note" data-roles-stub><?= $tr('common.admin.stub_note') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-admin-search
         placeholder="<?= $tr('common.admin.search_roles_ph') ?>"
         aria-label="<?= $tr('common.admin.search_roles_ph') ?>"
         autocomplete="off">
  <span class="admin-toolbar__count" data-admin-count><?= count($roles) ?></span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="roles" data-roles-table>
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_role') ?></th>
        <th scope="col"><?= $tr('common.admin.col_permissions') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
      </tr>
    </thead>
    <tbody data-roles-rows>
      <?php foreach ($roles as $row):
          $rid   = (int) $row['role']['id'];
          $title = (string) $row['role']['title'];
          $color = (string) ($row['role']['color'] ?? '');
          $sp    = $row['systemPermissions'];
          $sum   = (int) $sp['manageAccounts'] + (int) $sp['manageAdditionals']
                 + (int) $sp['managePeople'] + (int) $sp['manageRoles'];
          $search = mb_strtolower($title);
      ?>
        <tr data-role-row-id="<?= $rid ?>" data-search="<?= $view->e($search) ?>">
          <td class="admin-table__id"><?= $rid ?></td>
          <td>
            <span class="admin-role__title">
              <?php if ($color !== ''): ?>
                <span class="admin-role__dot" style="background: <?= $view->e($color) ?>"></span>
              <?php endif; ?>
              <?= $view->e($title) ?>
            </span>
          </td>
          <td>
            <span class="admin-badge admin-badge--accent" data-role-syscount>
              <?= $view->e($view->t('common.admin.role_sys_perms', ['count' => $sum])) ?>
            </span>
          </td>
          <td>
            <div class="admin-actions">
              <button type="button"
                      class="btn btn--secondary"
                      data-role-edit
                      data-role-id="<?= $rid ?>">
                <?= $tr('common.admin.action_permissions') ?>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <tr class="admin-table__empty" data-admin-empty<?= $roles ? ' hidden' : '' ?>>
        <td colspan="4"><?= $tr('common.admin.empty') ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Модалка прав роли: три группы чекбоксов (system / posts / pubs) -->
<?php $view->include('admin-role-modal'); ?>

<!-- Мок-массив ролей: admin.js читает его при открытии модалки и правках -->
<script type="application/json" data-roles-data><?= json_encode(
    $roles,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
