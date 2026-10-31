<?php
/**
 * admin-role-user-modal — модалка назначения ролей пользователям.
 */
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
?>
<div class="modal admin-role-user" data-role-user-modal hidden>
    <div class="modal__backdrop" data-role-user-close></div>

    <div class="modal__dialog admin-role__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="adminRoleUserTitle">
        <h2 class="modal__title" id="adminRoleUserTitle">
            <?= $tx('common.admin.role_assign_title', 'Assign roles to user') ?>
        </h2>

        <p class="admin-rolenew__error" data-role-user-error role="alert" hidden></p>

        <form data-role-user-form novalidate>
            <!-- 1. Дропдаун с пользователями -->
            <div class="form-field" style="margin-bottom: var(--space-3);">
                <label class="admin-role__group-title" for="adminRoleUserSelect">
                    <?= $tx('common.admin.role_select_user', 'User') ?>
                </label>
                <select class="form-field__input" id="adminRoleUserSelect" data-role-user-select required>
                    <option value="">— <?= $tx('common.admin.role_choose_user', 'Choose a user') ?> —</option>
                </select>
            </div>

            <p class="admin-role__loading" data-role-user-loading hidden>
                <?= $tx('common.common.loading', 'Loading...') ?>
            </p>

            <!-- 2. Список системных ролей для назначения -->
            <div class="admin-role__group" data-role-user-roles-wrap>
                <h3 class="admin-role__group-title">
                    <?= $tx('common.admin.role_assign_roles', 'Roles') ?>
                </h3>
                <div class="admin-role__list" data-role-user-roles-list>
                    <!-- Наполняется скриптом admin.js -->
                </div>
            </div>

            <div class="modal__actions admin-role__actions">
                <button type="button" class="btn btn--secondary" data-role-user-close>
                    <?= $tx('common.cancel', 'Cancel') ?>
                </button>
                <button type="submit" class="btn btn--primary" data-role-user-save disabled>
                    <?= $tx('common.admin.save', 'Save') ?>
                </button>
            </div>
        </form>
    </div>
</div>