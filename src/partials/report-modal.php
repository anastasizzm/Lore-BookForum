<?php
/**
 * report-modal — плашка «Пожаловаться» (выбор причины + необязательные
 * подробности). Одна на страницу, подключается в layouts/main.php, поэтому
 * работает везде: в шапке book/article details (пункт меню «три точки»)
 * и в меню «три точки» постов/комментариев/ответов (partials/comment-menu).
 *
 * ОТКРЫТИЕ (делегирование, public/assets/js/report-modal.js):
 *   <button data-report-open data-report-kind="book|article" data-report-id="N"
 *           data-report-title="Название"> — публикация (details-menu);
 *   <button data-report-open data-report-kind="post|comment|reply"> — комментарий:
 *     id и подпись JS находит сам по ближайшему [data-post-id],
 *     [data-comment-id] или [data-reply-id].
 *
 * ПУБЛИЧНЫЙ API JS: window.ReportModal.open({ kind, id, label }).
 *
 * ДАННЫЕ: причины — мок-список прямо в этой разметке (бэкенд заморожен,
 * config/ и app/ не трогаем); отправка тоже мок (см. TODO(api) в
 * report-modal.js), UI к контракту готов: POST /api/reports
 * { target_type, target_id, reason, comment } + _token (CSRF).
 *
 * Стили — public/assets/css/report-modal.css (каркас .modal — modal.css),
 * кнопки/поля — component.css.
 */
$reasons = [
    'spam'      => $view->t('common.report.reason_spam'),
    'abuse'     => $view->t('common.report.reason_abuse'),
    'nudity'    => $view->t('common.report.reason_nudity'),
    'plagiarism'=> $view->t('common.report.reason_plagiarism'),
    'spoiler'   => $view->t('common.report.reason_spoiler'),
    'other'     => $view->t('common.report.reason_other'),
];
?>
<div class="modal report-modal"
     data-report-modal
     hidden
     data-report-kind=""
     data-report-id=""
     data-report-label="">
    <div class="modal__backdrop" data-report-dismiss></div>

    <div class="modal__dialog report-modal__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="reportModalTitle"
         aria-describedby="reportModalHint">
        <h2 class="modal__title" id="reportModalTitle"><?= $view->e($view->t('common.report.title')) ?></h2>
        <p class="modal__text" id="reportModalHint"><?= $view->e($view->t('common.report.hint')) ?></p>

        <!-- На что именно жалуемся: вид + подпись (название/текст) -->
        <p class="report-modal__target" data-report-target hidden>
            <span class="report-modal__target-kind" data-report-kind-label></span>
            <span class="report-modal__target-title" data-report-title-label></span>
        </p>

        <div class="report-modal__body">
            <!-- Причины — обычные radio: выбор одной, Esc/Tab живут в modal.js-стиле -->
            <fieldset class="report-reasons" data-report-reasons>
                <legend class="report-reasons__legend"><?= $view->e($view->t('common.report.reasons_legend')) ?></legend>

                <?php foreach ($reasons as $value => $label): ?>
                    <label class="report-reason">
                        <input class="report-reason__input"
                               type="radio"
                               name="report-reason"
                               value="<?= $view->e($value) ?>">
                        <span class="report-reason__label"><?= $view->e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <!-- Ошибка отправки (в мок-режиме не показывается, но место зарезервировано) -->
            <p class="report-modal__error" data-report-error hidden role="alert"></p>

            <label class="report-modal__field-label" for="reportModalComment">
                <?= $view->e($view->t('common.report.comment_label')) ?>
            </label>
            <textarea class="report-modal__comment"
                      id="reportModalComment"
                      data-report-comment
                      rows="3"
                      maxlength="500"
                      placeholder="<?= $view->e($view->t('common.report.comment_ph')) ?>"></textarea>
        </div>

        <div class="modal__actions">
            <button type="button" class="btn btn--secondary" data-report-cancel>
                <?= $view->e($view->t('common.cancel')) ?>
            </button>
            <!-- Disabled, пока не выбрана причина (report-modal.js) -->
            <button type="button" class="btn btn--primary" data-report-confirm disabled>
                <span data-report-confirm-label><?= $view->e($view->t('common.report.send')) ?></span>
            </button>
        </div>
    </div>
</div>
