<?php
/**
 * admin-role-modal — модалка прав системной роли (admin/roles).
 *
 * Два режима (логика — admin/main.js, раздел ROLES):
 *   • «Permissions» в строке → правка прав роли (PUT /api/system/roles/{id});
 *   • «+» в тулбаре → виден [data-role-dropdown]: выбираем роль проекта
 *     (список отдаёт GET /api/additional/roles, без уже добавленных),
 *     отмечаем права, «Save» шлёт POST /api/system/roles
 *     { roleId, aboveId, belowId, systemPermissions, postPermissions, pubPermissions }.
 *
 * Назначение роли пользователю — отдельная модалка admin-role-user-modal.php.
 */
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
?>
<div class="modal admin-role-modal" data-role-modal hidden data-role-id="">
    <div class="modal__backdrop" data-role-close></div>

    <div class="modal__dialog admin-role__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="adminRoleTitle">
        <h2 class="modal__title" id="adminRoleTitle" data-role-title>
            <?= $tx('common.admin.role_modal_title', 'Permissions') ?>
        </h2>

        <!-- Режим «+»: выбор роли проекта (поле поиска + список под ним, как .roles-picker) -->
        <div class="admin-role__dropdown" data-role-dropdown hidden>
            <label class="admin-role__group-title" id="adminRoleDdLabel" for="adminRoleDdSearch">
                <?= $tx('common.admin.role_select_label', 'Role') ?>
            </label>
            <div class="admin-picker" data-role-dd>
                <div class="admin-picker__field">
                    <span class="admin-picker__dot" data-role-dd-dot hidden></span>
                    <input class="form-field__input admin-picker__input" id="adminRoleDdSearch"
                           type="search" data-role-dd-search
                           placeholder="<?= $tx('common.admin.role_search_ph', 'Search roles') ?>"
                           aria-label="<?= $tx('common.admin.role_search_ph', 'Search roles') ?>"
                           autocomplete="off" enterkeyhint="search"
                           role="combobox" aria-autocomplete="list" aria-expanded="false"
                           aria-controls="adminRoleDdList">
                    <svg class="admin-picker__caret" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
                        <path d="M3.5 6l4.5 4.5L12.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <ul class="admin-picker__results" id="adminRoleDdList" data-role-dd-list role="listbox"
                        aria-labelledby="adminRoleDdLabel" hidden></ul>
                </div>
            </div>
        </div>

        <p class="admin-role__loading" data-role-loading hidden>
            <?= $tx('common.common.loading', 'Loading...') ?>
        </p>

        <div class="admin-role__group" data-role-group="system">
            <h3 class="admin-role__group-title"><?= $tx('common.admin.role_group_system', 'System') ?></h3>
            <div class="admin-role__list" data-role-list="system"></div>
        </div>

        <div class="admin-role__group" data-role-group="posts">
            <h3 class="admin-role__group-title"><?= $tx('common.admin.role_group_posts', 'Posts') ?></h3>
            <div class="admin-role__list" data-role-list="posts"></div>
        </div>

        <div class="admin-role__group" data-role-group="pubs">
            <h3 class="admin-role__group-title"><?= $tx('common.admin.role_group_pubs', 'Publications') ?></h3>
            <div class="admin-role__list" data-role-list="pubs"></div>
        </div>

        <div class="modal__actions admin-role__actions">
            <button type="button" class="btn btn--secondary" data-role-close>
                <?= $tx('common.cancel', 'Cancel') ?>
            </button>
            <button type="button" class="btn btn--primary" data-role-save>
                <?= $tx('common.admin.save', 'Save') ?>
            </button>
        </div>
    </div>
</div>