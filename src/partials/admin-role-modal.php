<?php
/**
 * admin-role-modal — модалка прав системной роли для страницы
 * src/views/pages/admin/roles.php.
 *
 * Открывается кнопкой «Права» в строке таблицы ([data-role-edit]):
 * admin.js берёт копию роли из JSON-подложки [data-roles-data] и
 * наполняет модалку тремя группами чекбоксов, поэтому модалка одна
 * на таблицу.
 *
 * Группы чекбоксов (ключи прав — SystemAccessRules / PostAccessRules /
 * PublicationAccessRules, см. шапку admin.js и roles.php):
 *   [data-role-list="system"] — manageAccounts, manageAdditionals,
 *                               managePeople, manageRoles
 *   [data-role-list="posts"]  — create, update, delete
 *   [data-role-list="pubs"]   — create, updateMeta, updateContent, delete,
 *                               read, managePeople, manageRoles
 *
 * Кнопки внутри:
 *   [data-role-save] — PUT /api/system/roles/{roleId} c If-Match: version
 *                      (контракт в шапке roles.php); в режиме мока правки
 *                      применяются локально, версия инкрементируется.
 *   [data-role-close] — закрыть без сохранения.
 *
 * Стили — public/assets/css/admin.css (.admin-role__*), каркас .modal —
 * modal.css.
 */
?>
<div class="modal admin-role" data-role-modal hidden data-role-id="">
    <div class="modal__backdrop" data-role-close></div>

    <div class="modal__dialog admin-role__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="adminRoleTitle">
        <h2 class="modal__title" id="adminRoleTitle" data-role-title></h2>

        <p class="admin-role__loading" data-role-loading hidden>
            <?= $view->e($view->t('common.common.loading')) ?>
        </p>

        <div class="admin-role__group" data-role-group="system" hidden>
            <h3 class="admin-role__group-title">
                <?= $view->e($view->t('common.admin.role_sys_system')) ?>
            </h3>
            <div class="admin-role__list" data-role-list="system"></div>
        </div>

        <div class="admin-role__group" data-role-group="posts" hidden>
            <h3 class="admin-role__group-title">
                <?= $view->e($view->t('common.admin.role_sys_posts')) ?>
            </h3>
            <div class="admin-role__list" data-role-list="posts"></div>
        </div>

        <div class="admin-role__group" data-role-group="pubs" hidden>
            <h3 class="admin-role__group-title">
                <?= $view->e($view->t('common.admin.role_sys_pubs')) ?>
            </h3>
            <div class="admin-role__list" data-role-list="pubs"></div>
        </div>

        <div class="modal__actions admin-role__actions">
            <button type="button" class="btn btn--secondary" data-role-close>
                <?= $view->e($view->t('common.cancel')) ?>
            </button>
            <button type="button" class="btn btn--primary" data-role-save disabled>
                <?= $view->e($view->t('common.admin.role_save')) ?>
            </button>
        </div>
    </div>
</div>
