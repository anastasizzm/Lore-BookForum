<?php
/**
 * admin-report-modal — модалка решения по жалобе «Отклонить / Удалить»
 * для страницы src/views/pages/admin/reports.php.
 *
 * Открывается кнопкой «Решение» в строке таблицы ([data-admin-report-review]):
 * admin.js берёт сводку из data-атрибутов строки и заполняет поля ниже,
 * поэтому модалка одна на таблицу.
 *
 * Кнопки внутри:
 *   [data-admin-report-reject] — «Отклонить»: жалобу отклоняем, контент
 *                                остаётся (в моке — бейдж статуса);
 *   [data-admin-report-delete] — «Удалить»: контент удаляем, жалоба закрыта.
 * Обе — мок (см. TODO(api) в admin.js): PATCH /api/admin/reports/{id}
 * body { decision, note }. Заметка модератора необязательна.
 *
 * Стили — public/assets/css/admin.css (.admin-report__*), каркас .modal —
 * modal.css.
 */
?>
<div class="modal admin-report"
     data-admin-report-modal
     hidden
     data-report-id="">
    <div class="modal__backdrop" data-admin-report-dismiss></div>

    <div class="modal__dialog admin-report__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="adminReportTitle">
        <h2 class="modal__title" id="adminReportTitle" data-admin-report-title></h2>

        <!-- Сводка по жалобе: заполняет admin.js из data-атрибутов строки -->
        <dl class="admin-report__facts">
            <div class="admin-report__fact">
                <dt><?= $view->e($view->t('common.admin.decision_target')) ?></dt>
                <dd>
                    <span class="admin-badge admin-badge--accent" data-admin-report-kind></span>
                    <span class="admin-report__target" data-admin-report-target></span>
                </dd>
            </div>
            <div class="admin-report__fact">
                <dt><?= $view->e($view->t('common.admin.decision_reason')) ?></dt>
                <dd data-admin-report-reason></dd>
            </div>
            <div class="admin-report__fact">
                <dt><?= $view->e($view->t('common.admin.decision_reporter')) ?></dt>
                <dd data-admin-report-reporter></dd>
            </div>
            <div class="admin-report__fact">
                <dt><?= $view->e($view->t('common.admin.col_created')) ?></dt>
                <dd data-admin-report-created></dd>
            </div>
        </dl>

        <label class="admin-report__label" for="adminReportNote">
            <?= $view->e($view->t('common.admin.decision_note')) ?>
        </label>
        <textarea class="form-field__input admin-report__note"
                  id="adminReportNote"
                  data-admin-report-note
                  rows="3"
                  maxlength="300"
                  placeholder="<?= $view->e($view->t('common.admin.decision_note_ph')) ?>"></textarea>

        <div class="modal__actions admin-report__actions">
            <button type="button" class="btn btn--secondary" data-admin-report-cancel>
                <?= $view->e($view->t('common.cancel')) ?>
            </button>
            <!-- Отклонить — контент остаётся; Удалить — контент уходит -->
            <button type="button" class="btn btn--secondary" data-admin-report-reject>
                <?= $view->e($view->t('common.admin.action_reject')) ?>
            </button>
            <button type="button" class="btn btn--danger" data-admin-report-delete>
                <?= $view->e($view->t('common.admin.action_delete')) ?>
            </button>
        </div>
    </div>
</div>
