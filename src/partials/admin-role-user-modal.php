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
            <!-- 1. Пользователь: поле поиска + список под ним (как .roles-picker).
                 Скрытый select хранит выбранное значение для логики страницы. -->
            <div class="form-field" style="margin-bottom: var(--space-3);">
                <label class="admin-role__group-title" for="adminRoleUserSearch">
                    <?= $tx('common.admin.role_select_user', 'User') ?>
                </label>
                <div class="admin-picker" data-role-user-picker>
                    <div class="admin-picker__field">
                        <input class="form-field__input admin-picker__input" id="adminRoleUserSearch"
                               type="search" data-role-user-search
                               placeholder="<?= $tx('common.admin.role_user_search_ph', 'Search users') ?>"
                               aria-label="<?= $tx('common.admin.role_user_search_ph', 'Search users') ?>"
                               autocomplete="off" enterkeyhint="search"
                               role="combobox" aria-autocomplete="list" aria-expanded="false"
                               aria-controls="adminRoleUserResults">
                        <svg class="admin-picker__caret" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
                            <path d="M3.5 6l4.5 4.5L12.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <ul class="admin-picker__results" id="adminRoleUserResults" data-role-user-results role="listbox" hidden></ul>
                    </div>
                </div>
                <select data-role-user-select hidden tabindex="-1" aria-hidden="true">
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