<?php
/**
 * roles-module — модуль «назначение ролей пользователям» из Спецификации UI
 * (4 экрана). Встраивается в publish-диалоги:
 *
 *   - src/partials/rights-modal.php          (книги, book-form + book-details);
 *   - src/partials/article-rights-dialog.php (статьи, article-form + article-details).
 *
 * ЭКРАНЫ (data-roles-screen):
 *   1 — привязка: таблетка «Roles | Users», список ролей (порядок/ранг) или
 *       список пользователей с ролями, кнопка «+», переход к экрану 2
 *       (только когда добавлен хотя бы один пользователь), стрелка «назад»;
 *   2 — финальный: сводка «люди + роли» и кнопка «Publish» (Publish ведёт
 *       логику ХОСТА: права + публикация + редирект на details);
 *   3 — добавление/редактирование записи: поиск людей и ролей, чипы,
 *       «Save» / «Save and create another»;
 *   4 — права роли: сетка чекбоксов в две колонки, сохранение с If-Match.
 *
 * Логика:  public/assets/js/roles-module.js  (window.LoreRoles)
 * Стили:   public/assets/css/roles-module.css
 *
 * ХОСТ вызывает при открытии диалога:
 *   LoreRoles.open(root, { publicationId, mode, onPublish, onCancel, kind })
 * и при закрытии:
 *   LoreRoles.reset(root)
 *
 * mode: 'publish' — Publish завершает публикацию (onPublish хоста);
 *       'roles'   — Publish просто сохраняет роли (notify + закрыть).
 *
 * КОНТРАКТ С БЭКОМ (роуты уже есть, config/endpoints.php; app/ и config/
 * заморожены — контракт не меняется, недоступные ответы гасятся gracefully):
 *
 *   GET    /api/publications/{id}/roles
 *          → { items: [ { role: {id,title,color}, pubPermissions: {...},
 *                         canManage, version } ] }
 *   POST   /api/publications/{id}/roles          { roleId, aboveId?, belowId? }
 *   PATCH  /api/publications/{id}/roles/{rid}/position { aboveId, belowId }
 *          (+ If-Match: "<version>") — соседи, а не числовой ранг
 *   PUT    /api/publications/{id}/roles/{rid}    { pubPermissions } (+ If-Match)
 *   DELETE /api/publications/{id}/roles/{rid}
 *
 *   GET    /api/publications/{id}/users?include=roles&ps=50&page=1
 *          → { items: [ { id, username, roles: [...], canManage } ], meta }
 *   POST   /api/publications/{id}/users          { userId, roleId }
 *   DELETE /api/publications/{id}/users/{userId}
 *   GET    /api/publications/{id}/users/{uid}/roles
 *   POST   /api/publications/{id}/users/{uid}/roles    { roleId }
 *   DELETE /api/publications/{id}/users/{uid}/roles/{roleId}
 *
 *   GET    /api/system/users?ps=50&page=         — каталог людей для поиска
 *          (fallback: список привязанных пользователей публикации)
 *   GET    /api/system/roles                     — каталог ролей с названиями
 *          (для выбора при вставке роли; fallback: уже привязанные роли)
 *
 * Ошибка контракта показывается текстом и не роняет диалог (loading / error /
 * retry / empty), как в rights-modal.js и publication-roles.js.
 */
$tr = static fn(string $key, array $params = []): string => $view->e($view->t($key, $params));
?>
<div class="roles-module" data-roles-module data-api="unknown" data-mode="publish">

    <!-- ================= ЭКРАН 1. Привязка пользователей ================= -->
    <section class="roles-module__screen" data-roles-screen="1" aria-label="<?= $tr('common.roles.title') ?>">

        <header class="roles-module__head">
            <!-- Стрелка «назад»: из контекста диалога возвращает на форму -->
            <button type="button"
                    class="btn-icon roles-module__back"
                    data-roles-back
                    aria-label="<?= $tr('common.roles.back') ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 3 5 8l5 5"/>
                </svg>
            </button>

            <!-- Таблетка-переключатель «Roles | Users» (по центру) -->
            <div class="roles-pills" role="group" aria-label="<?= $tr('common.roles.title') ?>">
                <button type="button"
                        class="roles-pills__btn"
                        data-roles-tab="roles"
                        aria-pressed="false"><?= $tr('common.roles.tab_roles') ?></button>
                <button type="button"
                        class="roles-pills__btn is-active"
                        data-roles-tab="users"
                        aria-pressed="true"><?= $tr('common.roles.tab_users') ?></button>
            </div>

            <!-- «+» открывает экран 3 (добавление) -->
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

        <!-- Состояния загрузки -->
        <div class="roles-module__states">
            <p class="roles-module__loading" data-roles-loading>
                <span class="roles-module__spinner" aria-hidden="true"></span>
                <?= $tr('common.roles.loading') ?>
            </p>

            <div class="roles-module__error" data-roles-error hidden>
                <p class="roles-module__error-text" data-roles-error-text></p>
                <button type="button" class="btn btn--secondary" data-roles-retry>
                    <?= $tr('common.roles.retry') ?>
                </button>
            </div>

            <!-- Роуты ещё не развёрнуты (404/HTML) — работаем на мок-данных -->
            <p class="roles-module__note" data-roles-mock-note hidden>
                <?= $tr('common.roles.mock_note') ?>
            </p>
        </div>

        <!-- Тело: две страницы одного экрана -->
        <div class="roles-module__body" data-roles-body hidden>

            <!-- Страница «Roles»: порядок ролей (ранг), права, состав -->
            <div class="roles-module__page" data-roles-page="roles" hidden>
                <p class="roles-module__hint" data-roles-drag-hint><?= $tr('common.roles.drag_hint') ?></p>
                <ol class="roles-list" data-roles-list></ol>
                <p class="roles-module__empty" data-roles-empty="roles" hidden><?= $tr('common.roles.roles_empty') ?></p>
            </div>

            <!-- Страница «Users»: пользователи с привязанными ролями -->
            <div class="roles-module__page" data-roles-page="users" hidden>
                <ul class="roles-users" data-roles-users></ul>
                <p class="roles-module__empty" data-roles-empty="users" hidden><?= $tr('common.roles.users_empty') ?></p>
                <p class="roles-module__hint" data-roles-empty-hint="users" hidden><?= $tr('common.roles.users_empty_hint') ?></p>
            </div>
        </div>

        <footer class="roles-module__foot">
            <p class="roles-module__gate" data-roles-gate hidden><?= $tr('common.roles.gate_hint') ?></p>
            <button type="button" class="btn btn--primary" data-roles-next><?= $tr('common.roles.continue') ?></button>
        </footer>
    </section>

    <!-- ================= ЭКРАН 2. Финальный (Publish) ================= -->
    <section class="roles-module__screen" data-roles-screen="2" hidden aria-label="<?= $tr('common.roles.screen2_title') ?>">

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
            <span class="roles-module__title"><?= $tr('common.roles.screen2_title') ?></span>
            <span class="roles-module__spacer" aria-hidden="true"></span>
        </header>

        <p class="roles-module__hint"><?= $tr('common.roles.screen2_hint') ?></p>

        <div class="roles-module__body">
            <h4 class="roles-module__subtitle"><?= $tr('common.roles.users_label') ?></h4>
            <ul class="roles-summary" data-roles-summary="users"></ul>

            <h4 class="roles-module__subtitle"><?= $tr('common.roles.roles_label') ?></h4>
            <ul class="roles-summary" data-roles-summary="roles"></ul>
        </div>

        <footer class="roles-module__foot roles-module__foot--split">
            <button type="button" class="btn btn--secondary" data-roles-screen-back="1">
                <?= $tr('common.roles.back') ?>
            </button>
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

    <!-- ================= ЭКРАН 3. Добавление (модалка) ================= -->
    <div class="roles-overlay" data-roles-add hidden>
        <div class="roles-overlay__backdrop" data-roles-add-dismiss></div>

        <div class="roles-overlay__dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="rolesAddTitle">
            <h2 class="roles-overlay__title" id="rolesAddTitle" data-roles-add-title>
                <?= $tr('common.roles.add_title') ?>
            </h2>

            <p class="roles-overlay__hint"><?= $tr('common.roles.chips_hint') ?></p>

            <!-- Поиск по пользователям: строка + выпадающий список результатов -->
            <div class="roles-picker">
                <label class="roles-picker__label" for="rolesAddUsers"><?= $tr('common.roles.users_label') ?></label>
                <input type="search"
                       class="form-field__input roles-picker__input"
                       id="rolesAddUsers"
                       data-roles-user-search
                       autocomplete="off"
                       placeholder="<?= $tr('common.roles.search_users_ph') ?>">
                <ul class="roles-picker__results" data-roles-user-results hidden></ul>
                <ul class="roles-chips" data-roles-user-chips></ul>
            </div>

            <!-- Поиск по ролям: аналогично, выбор из каталога бэка -->
            <div class="roles-picker">
                <label class="roles-picker__label" for="rolesAddRoles"><?= $tr('common.roles.roles_label') ?></label>
                <input type="search"
                       class="form-field__input roles-picker__input"
                       id="rolesAddRoles"
                       data-roles-role-search
                       autocomplete="off"
                       placeholder="<?= $tr('common.roles.search_roles_ph') ?>">
                <ul class="roles-picker__results" data-roles-role-results hidden></ul>
                <ul class="roles-chips" data-roles-role-chips></ul>
            </div>

            <p class="roles-overlay__status" data-roles-add-status hidden aria-live="polite"></p>

            <div class="roles-overlay__actions">
                <button type="button" class="btn btn--secondary" data-roles-add-cancel>
                    <?= $tr('common.cancel') ?>
                </button>
                <button type="button" class="btn btn--secondary" data-roles-add-save-more>
                    <?= $tr('common.roles.save_more') ?>
                </button>
                <button type="button" class="btn btn--primary" data-roles-add-save>
                    <?= $tr('common.roles.save') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= ЭКРАН 4. Права ролей (модалка) ================= -->
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
