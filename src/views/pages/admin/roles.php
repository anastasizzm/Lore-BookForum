<?php
/**
 * admin/roles — страница системных ролей админки.
 */
$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
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

<p class="admin-toolbar__status" data-roles-status role="status" aria-live="polite"><?= $tr('common.admin.roles_loading') ?></p>

<div class="admin-toolbar admin-toolbar--end">
  <button type="button"
          class="btn btn--primary admin-toolbar__add admin-toolbar__add--icon"
          data-role-add
          title="<?= $tx('common.admin.role_attach_title', 'Add role to project') ?>"
          aria-label="<?= $tx('common.admin.role_attach_title', 'Add role to project') ?>">
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="9" y1="3" x2="9" y2="15"></line>
      <line x1="3" y1="9" x2="15" y2="9"></line>
    </svg>
  </button>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="roles" data-roles-table>
    <thead>
      <tr>
        <th scope="col" class="admin-table__drag" aria-label="<?= $tx('common.admin.role_drag', 'Drag to reorder') ?>"></th>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_role') ?></th>
        <th scope="col"><?= $tr('common.admin.col_permissions') ?></th>
        <th scope="col"><?= $tr('common.admin.col_delete') ?></th>
      </tr>
    </thead>
    <tbody data-roles-rows>
      <tr class="admin-table__empty" data-admin-empty hidden>
        <td colspan="5"><?= $tr('common.admin.empty') ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Модалка прав роли / добавления роли в проект -->
<?php $view->include('admin-role-modal'); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>