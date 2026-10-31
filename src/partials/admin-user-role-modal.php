<?php
/**
 * admin-user-role-modal — «Добавить роль пользователю» (admin/users).
 *
 * Открывается кнопкой «+» во вложенном списке ролей строки пользователя
 * ([data-user-role-add]); имя пользователя подставляет admin.js (раздел USER ROLES).
 * Дропдаун: поиск уходит в API (GET /api/additional/roles?q=…) только по Enter.
 * Save → POST /api/system/users/{id}/roles { roleId }.
 */
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
?>
<div class="modal admin-urole" data-urole-modal hidden>
    <div class="modal__backdrop" data-urole-close></div>

    <div class="modal__dialog admin-role__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="adminUroleTitle">
        <h2 class="modal__title" id="adminUroleTitle">
            <?= $tx('common.admin.user_role_add_title', 'Add role to user') ?>
        </h2>

        <div class="admin-urole__user">
            <span class="admin-role__group-title"><?= $tx('common.admin.role_select_user', 'User') ?></span>
            <div class="admin-urole__user-name" data-urole-user></div>
        </div>

        <div class="admin-role__dropdown">
            <span class="admin-role__group-title" id="adminUroleDdLabel">
                <?= $tx('common.admin.role_select_label', 'Role') ?>
            </span>
            <div class="admin-role__dd" data-urole-dd>
                <button type="button" class="admin-role__dd-toggle" data-urole-dd-toggle
                        aria-haspopup="listbox" aria-expanded="false" aria-labelledby="adminUroleDdLabel">
                    <span class="admin-role__dd-value is-placeholder" data-urole-dd-value>
                        <?= $tx('common.admin.role_select_placeholder', 'Choose a role') ?>
                    </span>
                    <svg class="admin-role__dd-chevron" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
                        <path d="M3.5 6l4.5 4.5L12.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="admin-role__dd-menu" data-urole-dd-menu hidden>
                    <input class="admin-role__dd-search" type="search" data-urole-dd-search
                           placeholder="<?= $tx('common.admin.role_search_ph', 'Search roles') ?>"
                           aria-label="<?= $tx('common.admin.role_search_ph', 'Search roles') ?>"
                           autocomplete="off" enterkeyhint="search">
                    <ul class="admin-role__dd-list" data-urole-dd-list role="listbox" aria-labelledby="adminUroleDdLabel"></ul>
                </div>
            </div>
        </div>

        <div class="modal__actions admin-role__actions">
            <button type="button" class="btn btn--secondary" data-urole-close>
                <?= $tx('common.cancel', 'Cancel') ?>
            </button>
            <button type="button" class="btn btn--primary" data-urole-save disabled>
                <?= $tx('common.admin.save', 'Save') ?>
            </button>
        </div>
    </div>
</div>