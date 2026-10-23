<?php
/**
 * confirm-modal — универсальная плашка подтверждения («Вы уверены?»).
 *
 * Плашка по центру экрана, фон затемнён. Одна на страницу, лежит в main-layout.
 *
 * Использование (см. public/assets/js/modal.js):
 *   1. Программно — ConfirmModal.confirm({title, message, confirmText, danger})
 *      возвращает Promise<boolean>; подходит для удаления поста и т.п.
 *   2. Декларативно — атрибуты data-confirm* на кнопке или ссылке
 *      (сейчас так подтверждается Log out в src/partials/sidebar.php).
 */
// Заголовок и кнопки подставляются в модалку, когда её открывает modal.js
// (defaults() читает их из DOM) — поэтому они обязаны быть переведены,
// иначе на локали ru в модалке останется английский fallback.
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
?>
<div class="modal" data-confirm-modal hidden>
    <div class="modal__backdrop" data-modal-dismiss></div>

    <div class="modal__dialog"
         role="alertdialog"
         aria-modal="true"
         aria-labelledby="confirmModalTitle"
         aria-describedby="confirmModalText">
        <h2 class="modal__title" id="confirmModalTitle" data-modal-title><?= $tr('common.confirm.title') ?></h2>
        <p class="modal__text" id="confirmModalText" data-modal-text></p>

        <div class="modal__actions">
            <button type="button" class="btn btn--secondary" data-modal-cancel><?= $tr('common.cancel') ?></button>
            <button type="button" class="btn btn--primary" data-modal-confirm><?= $tr('common.confirm.ok') ?></button>
        </div>
    </div>
</div>
