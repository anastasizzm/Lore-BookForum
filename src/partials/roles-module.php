<?php
/**
 * roles-module — модуль «назначение ролей пользователям» из Спецификации UI.
 *
 * Типы экранов (по спецуфикации):
 *   - Страницы (не модалки):
 *       1 — «Первый экран»: страницы ролей публикации
 *          (список ролей, кнопка «+» → модалка создания роли, кнопка Continue;
 *          Continue есть при создании и отсутствует при изменении);
 *       2 — «Второй экран»: страницы пользователей публикации
 *          (список пользователей + поиск, кнопка «+» → модалка «роль + пользователь»,
 *          кнопка Publish).
 *   - Модальные окна:
 *       — «Экран создания роли»: выпадающий список ролей (поиск + список),
 *         список чекбоксов с правами для публикации, Add, Cancel;
 *       — «Экран создания "роль + пользователь"»: выпадающий список ролей,
 *         выпадающий список пользователей, Add, Cancel.
 *
 * Встраивается в publish-диалоги:
 *
 *   - src/partials/rights-modal.php          (книги, book-form + book-details);
 *   - src/partials/article-rights-dialog.php (статьи, article-form + article-details).
 *
 * Логика:  public/assets/js/roles-module.js  (window.LoreRoles)
 * Стили:   public/assets/css/roles-module.css
 *
 * ХОСТ вызывает при открытии диалога:
 *   LoreRoles.open(root, { publicationId, mode, context, onPublish, onCancel, kind })
 * и при закрытии:
 *   LoreRoles.reset(root)
 *
 * context: 'create' — экран 1 (роли) → Continue → экран 2 (люди) → Publish
 *          'edit'   — Continue на экране 1 не показывается (редактирование).
 *
 * mode: 'publish' — Publish завершает публикацию (onPublish хоста);
 *       'roles'   — Publish просто сохраняет роли (notify + закрыть).
 */

$tr = static fn(string $key, array $params = []): string => $view->e($view->t($key, $params));
?>
<div class="roles-module" data-roles-module data-api="unknown" data-mode="publish">

    <!-- ================= ЭКРАН 1. Страница ролей публикации ================= -->
    <section class="roles-module__screen" data-roles-screen="1" aria-label="<?= $tr('common.roles.title') ?>">

        <header class="roles-module__head">
            <button type="button"
                    class="btn-icon roles-module__back"
                    data-roles-back
                    aria-label="<?= $tr('common.roles.back') ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 3 5 8l5 5"/>
                </svg>
            </button>

            <span class="roles-module__title"><?= $tr('common.roles.title') ?></span>

            <!-- «+» открывает модалку создания роли -->
            <button type="button"
                    class="btn-icon roles-module__add"
                    data-roles-add-open
                    aria-label="<?= $tr('common.roles.add') ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M8 3.5v9M3.5 8h9"/>
                </svg>
            </button>
        </header>

        <div class="roles-module__body" data-roles-body hidden>
            <p class="roles-module__hint" data-roles-drag-hint><?= $tr('common.roles.drag_hint') ?></p>
            <ol class="roles-list" data-roles-list></ol>
            <p class="roles-module__empty" data-roles-empty="roles" hidden><?= $tr('common.roles.roles_empty') ?></p>
        </div>

        <footer class="roles-module__foot">
            <button type="button" class="btn btn--primary" data-roles-next><?= $tr('common.roles.continue') ?></button>
        </footer>
    </section>

    <!-- ================= ЭКРАН 2. Страница пользователей публикации ================= -->
    <section class="roles-module__screen" data-roles-screen="2" hidden aria-label="<?= $tr('common.roles.users_label') ?>">

        <header class="roles-module__head">
            <button type="button"
                    class="btn-icon roles-module__back"
                    data-roles-screen-back="1"
                    aria-label="<?= $tr('common.roles.back') ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 3 5 8l5 5"/>
                </svg>
            </button>
            <span class="roles-module__title"><?= $tr('common.roles.users_label') ?></span>
            <span class="roles-module__spacer" aria-hidden="true"></span>

            <!-- «+» открывает модалку добавления пользователя (роль назначается одна) -->
            <button type="button"
                    class="btn-icon roles-module__add"
                    data-roles-add-open
                    aria-label="<?= $tr('common.roles.add_user') ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M8 3.5v9M3.5 8h9"/>
                </svg>
            </button>
        </header>

        <div class="roles-module__body" data-roles-users-body hidden>
            <div class="roles-module__page" data-roles-page="users">
                <input type="search"
                       class="form-field__input roles-picker__input"
                       id="rolesUsersSearch"
                       data-roles-user-search
                       autocomplete="off"
                       placeholder="<?= $tr('common.roles.search_users_ph') ?>">

                <!-- Таблица людей как на макете: USERNAME | ROLE (+ шеврон раскрытия) -->
                <div class="roles-users-table" data-roles-users-table hidden>
                    <div class="roles-users-table__head">
                        <span class="roles-users-table__th"><?= $tr('common.roles.users_label') ?></span>
                        <span class="roles-users-table__th"><?= $tr('common.roles.roles_label') ?></span>
                        <span class="roles-users-table__th roles-users-table__th--chev"></span>
                    </div>
                    <ul class="roles-users" data-roles-users></ul>
                </div>
                <p class="roles-module__empty" data-roles-empty="users" hidden><?= $tr('common.roles.users_empty') ?></p>
                <p class="roles-module__hint" data-roles-empty-hint="users" hidden><?= $tr('common.roles.users_empty_hint') ?></p>
            </div>
        </div>

        <footer class="roles-module__foot">
            <button type="button" class="btn btn--primary" data-roles-publish>
                <?= $tr('common.roles.publish') ?>
            </button>
        </footer>
    </section>

    <!-- ============ ВСТАВКА РОЛИ («+» между строками, экран 1) ============ -->
    <div class="roles-overlay" data-roles-insert hidden>
        <div class="roles-overlay__backdrop" data-roles-insert-dismiss></div>

        <div class="roles-overlay__dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="rolesInsertTitle">
            <h2 class="roles-overlay__title" id="rolesInsertTitle"><?= $tr('common.roles.insert_title') ?></h2>
            <p class="roles-overlay__hint"><?= $tr('common.roles.insert_hint') ?></p>

            <ul class="roles-insert__list" data-roles-insert-list></ul>

            <p class="roles-overlay__status" data-roles-insert-status hidden aria-live="polite"></p>

            <div class="roles-overlay__actions">
                <button type="button" class="btn btn--secondary" data-roles-insert-cancel>
                    <?= $tr('common.cancel') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= МОДАЛКА: создание роли ================= -->
    <div class="roles-overlay" data-roles-create-role hidden>
        <div class="roles-overlay__backdrop" data-roles-create-role-dismiss></div>

        <div class="roles-overlay__dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="rolesCreateTitle">
            <h2 class="roles-overlay__title" id="rolesCreateTitle" data-roles-create-title>
                <?= $tr('common.roles.create_role_title') ?>
            </h2>

            <!-- ROLE: поле-поиск с выпадающим списком ролей каталога -->
            <div class="roles-picker roles-picker--select">
                <label class="roles-picker__label roles-picker__label--caps" for="rolesCreateRole"><?= $tr('common.roles.role_label') ?></label>
                <div class="roles-select">
                    <input type="search"
                           class="form-field__input roles-picker__input roles-select__input"
                           id="rolesCreateRole"
                           data-roles-create-role-search
                           autocomplete="off"
                           role="combobox"
                           aria-expanded="false"
                           aria-autocomplete="list"
                           placeholder="<?= $tr('common.roles.choose_role') ?>">
                    <svg class="roles-select__caret" width="12" height="12" viewBox="0 0 12 12" fill="none"
                         stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M3 4.5 6 7.5l3-3"/>
                    </svg>
                </div>
                <ul class="roles-picker__results" data-roles-create-role-results hidden></ul>
            </div>

            <!-- Все права публикации одной группой (без категорий) -->
            <div class="roles-perms">
                <div class="roles-perms__group" data-perms-group="pub" hidden>
                    <h4 class="roles-perms__group-title"><?= $tr('common.roles.perms_group_pub') ?></h4>
                    <div class="roles-perms__list" data-roles-create-perms-list="pub"></div>
                </div>
            </div>

            <p class="roles-overlay__status" data-roles-create-status hidden aria-live="polite"></p>

            <div class="roles-overlay__actions">
                <button type="button" class="btn btn--secondary" data-roles-create-cancel>
                    <?= $tr('common.cancel') ?>
                </button>
                <button type="button" class="btn btn--primary" data-roles-create-add>
                    <?= $tr('common.roles.create_add') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ============ МОДАЛКА: добавление роли пользователю ============ -->
    <div class="roles-overlay" data-roles-create-user hidden>
        <div class="roles-overlay__backdrop" data-roles-create-user-dismiss></div>

        <div class="roles-overlay__dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="rolesCreateUserTitle">
            <h2 class="roles-overlay__title" id="rolesCreateUserTitle" data-roles-create-user-title>
                <?= $tr('common.roles.create_user_title') ?>
            </h2>
            <p class="roles-overlay__hint" data-roles-create-user-hint><?= $tr('common.roles.create_user_hint') ?></p>

            <!-- USER: поиск, одиночный выбор -->
            <div class="roles-picker" data-roles-create-user-user-picker>
                <label class="roles-picker__label" for="rolesCreateUserUser"><?= $tr('common.roles.users_label') ?></label>
                <input type="search"
                       class="form-field__input roles-picker__input"
                       id="rolesCreateUserUser"
                       data-roles-create-user-user-search
                       autocomplete="off"
                       placeholder="<?= $tr('common.roles.search_users_ph') ?>">
                <ul class="roles-picker__results" data-roles-create-user-user-results hidden></ul>
            </div>

            <!-- ROLE: поле-поиск с выпадающим списком (как у пользователей); роль только одна -->
            <div class="roles-picker roles-picker--select" data-roles-create-user-role-picker>
                <label class="roles-picker__label" for="rolesCreateUserRole"><?= $tr('common.roles.roles_label') ?></label>
                <div class="roles-select">
                    <input type="search"
                           class="form-field__input roles-picker__input roles-select__input"
                           id="rolesCreateUserRole"
                           data-roles-create-user-role-search
                           autocomplete="off"
                           role="combobox"
                           aria-expanded="false"
                           aria-autocomplete="list"
                           placeholder="<?= $tr('common.roles.choose_role') ?>">
                    <svg class="roles-select__caret" width="12" height="12" viewBox="0 0 12 12" fill="none"
                         stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M3 4.5 6 7.5l3-3"/>
                    </svg>
                </div>
                <ul class="roles-picker__results" data-roles-create-user-role-results hidden></ul>
            </div>

            <p class="roles-overlay__status" data-roles-create-user-status hidden aria-live="polite"></p>

            <div class="roles-overlay__actions">
                <button type="button" class="btn btn--secondary" data-roles-create-user-cancel>
                    <?= $tr('common.cancel') ?>
                </button>
                <button type="button" class="btn btn--primary" data-roles-create-user-add>
                    <?= $tr('common.roles.create_user_add') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= МОДАЛКА: права роли (редактирование существующей) ================= -->
    <div class="roles-overlay" data-roles-perms hidden>
        <div class="roles-overlay__backdrop" data-roles-perms-dismiss></div>

        <div class="roles-overlay__dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="rolesPermsTitle">
            <h2 class="roles-overlay__title" id="rolesPermsTitle" data-roles-perms-title>
                <?= $tr('common.roles.perms_title') ?>
            </h2>

            <p class="roles-overlay__hint"><?= $tr('common.roles.perms_hint') ?></p>

            <!-- Сетка чекбоксов в две колонки; группы заполняет roles-module.js -->
            <div class="roles-perms" data-roles-perms-body>
                <div class="roles-perms__group" data-perms-group="pub" hidden>
                    <h4 class="roles-perms__group-title"><?= $tr('common.roles.perms_group_pub') ?></h4>
                    <div class="roles-perms__list" data-perms-list="pub"></div>
                </div>
            </div>

            <p class="roles-overlay__status" data-roles-perms-status hidden aria-live="polite"></p>

            <div class="roles-overlay__actions">
                <button type="button" class="btn btn--secondary" data-roles-perms-cancel>
                    <?= $tr('common.cancel') ?>
                </button>
                <button type="button" class="btn btn--primary" data-roles-perms-save>
                    <?= $tr('common.roles.perms_save') ?>
                </button>
            </div>
        </div>
    </div>
</div>
