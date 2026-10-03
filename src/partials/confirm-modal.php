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
?>
<div class="modal" data-confirm-modal hidden>
    <div class="modal__backdrop" data-modal-dismiss></div>

    <div class="modal__dialog"
         role="alertdialog"
         aria-modal="true"
         aria-labelledby="confirmModalTitle"
         aria-describedby="confirmModalText">
        <h2 class="modal__title" id="confirmModalTitle" data-modal-title>Are you sure?</h2>
        <p class="modal__text" id="confirmModalText" data-modal-text></p>

        <div class="modal__actions">
            <button type="button" class="btn btn--secondary" data-modal-cancel>Cancel</button>
            <button type="button" class="btn btn--primary" data-modal-confirm>Confirm</button>
        </div>
    </div>
</div>
