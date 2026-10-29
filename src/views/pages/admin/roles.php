<?php
$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?>Системные роли — <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/admin-role.css') ?>">
  <?php /* permissions.js — надстройка над LoreRules (rules.js уже в head.php).
           Нужен для data-requires="system.manageRoles" на кнопке «+ Создать роль».
           Порядок: rules.js (head.php) -> permissions.js -> admin-roles.js */ ?>
  <script src="<?= $view->asset('js/permissions.js') ?>" defer></script>
  <script src="<?= $view->asset('js/admin-roles.js') ?>" defer></script>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => 'Системные роли',
    'subtitle' => 'Перетаскивайте роли для изменения приоритета.',
]); ?>

<div class="admin-roles" data-roles-container data-api="/api/system/roles">
  <div class="admin-roles__header">
    <button type="button"
            class="btn btn--primary"
            data-role-create
            data-requires="system.manageRoles">+ Создать роль</button>
    <div class="admin-roles__sync">
      <span class="admin-roles__spinner" data-roles-spinner hidden aria-hidden="true"></span>
    </div>
  </div>

  <ul class="admin-roles__list" data-roles-list aria-live="polite">
    <li class="admin-roles__loading">Загрузка ролей...</li>
  </ul>
</div>

<!-- Модалка создания/редактирования -->
<div class="modal"
     data-role-modal
     role="dialog"
     aria-modal="true"
     aria-labelledby="role-modal-title"
     hidden>
  <div class="modal__backdrop" data-role-modal-close></div>
  <div class="modal__dialog">
    <h2 class="modal__title" id="role-modal-title" data-role-modal-title>Создание роли</h2>

    <form data-role-form novalidate>
      <input type="hidden" name="id" value="">
      <input type="hidden" name="version" value="">

      <div class="form-field">
        <label for="role-name">Название роли</label>
        <input type="text" id="role-name" name="name" class="form-field__input" required placeholder="Например, Редактор">
      </div>

      <div class="admin-role-flags-grid">
        <fieldset>
          <legend>Права системы (System)</legend>
          <label><input type="checkbox" name="systemPermissions[manageAccounts]"> Управление аккаунтами</label>
          <label><input type="checkbox" name="systemPermissions[manageAdditionals]"> Управление справочниками</label>
          <label><input type="checkbox" name="systemPermissions[managePeople]"> Управление людьми</label>
          <label><input type="checkbox" name="systemPermissions[manageRoles]"> Управление ролями</label>
        </fieldset>

        <fieldset>
          <legend>Права постов (Post)</legend>
          <label><input type="checkbox" name="postPermissions[create]"> Создавать</label>
          <label><input type="checkbox" name="postPermissions[update]"> Редактировать</label>
          <label><input type="checkbox" name="postPermissions[delete]"> Удалять</label>
        </fieldset>

        <fieldset>
          <legend>Права публикаций (Pub)</legend>
          <label><input type="checkbox" name="pubPermissions[create]"> Создавать</label>
          <label><input type="checkbox" name="pubPermissions[read]"> Читать</label>
          <label><input type="checkbox" name="pubPermissions[updateMeta]"> Изменять мету</label>
          <label><input type="checkbox" name="pubPermissions[updateContent]"> Изменять контент</label>
          <label><input type="checkbox" name="pubPermissions[delete]"> Удалять</label>
          <label><input type="checkbox" name="pubPermissions[managePeople]"> Управлять людьми</label>
          <label><input type="checkbox" name="pubPermissions[manageRoles]"> Управлять ролями</label>
        </fieldset>
      </div>

      <div class="modal__actions">
        <button type="button" class="btn btn--secondary" data-role-modal-close>Отмена</button>
        <button type="submit" class="btn btn--primary" data-role-submit>Сохранить</button>
      </div>
    </form>
  </div>
</div>

<?php $view->endBlock('content'); ?>