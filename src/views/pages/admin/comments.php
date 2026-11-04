<?php
/**
 * admin/all-roles — страница ВСЕХ ролей (названий).
 */
$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.all_roles_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('admin-back'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.all_roles_title'),
    'subtitle' => $view->t('common.admin.all_roles_subtitle'),
]); ?>

<p class="admin-toolbar__status" data-allroles-status role="status" aria-live="polite"><?= $tr('common.admin.roles_loading') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-allroles-search
         placeholder="<?= $tr('common.admin.search_roles_ph') ?>"
         aria-label="<?= $tr('common.admin.search_roles_ph') ?>"
         enterkeyhint="search"
         autocomplete="off">
         
  <button type="button"
          class="btn btn--primary admin-toolbar__add admin-toolbar__add--icon"
          data-rolenew-open
          title="<?= $tr('common.admin.role_new_title') ?>"
          aria-label="<?= $tr('common.admin.role_new_title') ?>">
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="9" y1="3" x2="9" y2="15"></line>
      <line x1="3" y1="9" x2="15" y2="9"></line>
    </svg>
  </button>

  <div class="admin-seg" role="radiogroup" data-allroles-filter-status
       aria-label="<?= $tx('common.admin.roles_filter_status', 'Status') ?>">
    <label><input type="radio" name="allroles-status" value="all" checked><span><?= $tx('common.admin.role_status_all', 'All') ?></span></label>
    <label><input type="radio" name="allroles-status" value="public"><span><?= $tx('common.admin.role_public', 'Public') ?></span></label>
    <label><input type="radio" name="allroles-status" value="private"><span><?= $tx('common.admin.role_private', 'Private') ?></span></label>
  </div>
  <span class="admin-toolbar__count" data-allroles-count>0</span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-allroles-table>
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_role') ?></th>
        <th scope="col"><?= $tx('common.admin.col_visibility', 'Visibility') ?></th>
        <th scope="col"><?= $tr('common.admin.col_delete') ?></th>
      </tr>
    </thead>
    <tbody data-allroles-rows>
      <tr class="admin-table__empty" data-allroles-empty hidden>
        <td colspan="4"><?= $tr('common.admin.empty') ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Модалка создания новой роли -->
<?php $view->include('admin-role-create-modal'); ?>

<?php
/*
 * Данные страницы уже есть у контроллера — отдаём их в разметку, чтобы первая
 * загрузка таблицы не делала запрос к API (JS читает блок один раз и удаляет;
 * поиск, фильтры и «Показать ещё» дальше идут через API как раньше).
 * Нет данных / не сериализуются — блок не выводится, JS сам сходит в API.
 */
$adminBoot = static function (string $name, mixed $data): void {
    // null = контроллер данные не передал (JS сходит в API); пустой массив = «ролей нет»
    if ($data === null) return;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($json)) return;
    echo '<script type="application/json" data-admin-data="' . $name . '">' . $json . '</script>' . "\n";
};
$adminBoot('all-roles', $allRoles ?? $names ?? null);
?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script type="module" src="<?= $view->asset('js/admin/main.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>