<?php
/**
 * rights-modal — плашка публикации книги с выбором прав.
 *
 * Использование (общая для двух страниц, см. JS public/assets/js/rights-modal.js):
 *   1. Книжная форма (book-form): открытие программно после УСПЕХА публикации —
 *      RightsModal.open({ mode: 'after-publish', bookId, redirect }).
 *   2. Страница книги (book-details): кнопка «Опубликовать книгу» у черновика
 *      с атрибутом data-rights-open — открытие кликом (mode = 'publish').
 *
 * Данные атрибуты:
 *   data-rights-modal — корень плашки (одна на страницу)
 *   data-rights-mode  — 'after-publish' | 'publish' (заполняет JS при открытии)
 *   data-book-id      — id книги
 *   data-redirect     — куда вести после успеха/отмены (режим after-publish)
 *
 * КОНТРАКТ С БЭКОМ (P0 — роуты добавит бэк: config/ и app/ заморожены):
 *
 *   GET  /api/additional/rights         — справочник прав (грузится АСИНХРОННО
 *        при открытии плашки; по аналогии с /api/additional/genres,
 *        формат ResponseTemplates::list):
 *        200 { items: [ { id: int, title: string, description?: string } ] }
 *        401/404/5xx → состояние ошибки + «Повторить» (плашка не ломается).
 *
 *   POST /api/publications/{id}/rights  — сохранение выбранных прав:
 *        enctype form, поля: rights[] (array<int>), _token (CSRF);
 *        успех: 200/204, ошибки: 422 { errors: { rights: [msg] } }.
 *
 *   POST /books/{id}/publish            — перевод черновика в published;
 *        используется ТОЛЬКО в режиме data-rights-mode="publish"
 *        (кнопка «Опубликовать книгу» на book-details и кнопка
 *        «Опубликовать» в форме редактирования книги): после успеха
 *        rights[] и publish страница уходит на /books/{id} — статус
 *        меняется на published, бейдж и кнопка исчезают.
 *
 *   Режим after-publish (из формы создания): книга УЖЕ опубликована
 *   ответом POST /books/store — плашка только досылает rights[] и ведёт
 *   на страницу книги (data-redirect).
 *
 * Статус черновика и права доступа (для book-details):
 *   черновик виден только создателю — политику держит бэк ( BooksController );
 *   статус $status ('draft'|'published') контроллер передаёт в вьюху
 *   напрямую; кнопку «Редактировать» вьюха НЕ показывает сама — пункт
 *   рендерится скрытым (hidden), показывает его permissions.js по
 *   data-requires (см. шапку book-details.php).
 *
 * Стили — public/assets/css/rights-modal.css (каркас .modal — modal.css).
 */
?>
<div class="modal rights-modal" data-rights-modal hidden
     data-rights-mode=""
     data-book-id="0"
     data-redirect="">
    <div class="modal__backdrop" data-rights-dismiss></div>

    <div class="modal__dialog rights-modal__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="rightsModalTitle">
        <h2 class="modal__title" id="rightsModalTitle" data-rights-title><?= $view->e($view->t('common.rights.title')) ?></h2>
        <p class="modal__text" data-rights-hint><?= $view->e($view->t('common.rights.hint')) ?></p>

        <!-- Тело: три состояния — загрузка / ошибка / список -->
        <div class="rights-modal__body">

            <div class="rights-modal__loading" data-rights-loading hidden>
                <span class="rights-modal__spinner" aria-hidden="true"></span>
                <span><?= $view->e($view->t('common.rights.loading')) ?></span>
            </div>

            <div class="rights-modal__error" data-rights-error hidden>
                <p class="rights-modal__error-text" data-rights-error-text></p>
                <button type="button" class="btn btn--secondary" data-rights-retry>
                    <?= $view->e($view->t('common.rights.retry')) ?>
                </button>
            </div>

            <ul class="rights-list" data-rights-list hidden></ul>

            <p class="rights-modal__empty" data-rights-empty hidden>
                <?= $view->e($view->t('common.rights.empty')) ?>
            </p>

            <!-- Ошибка сохранения прав (POST не прошёл) -->
            <p class="rights-modal__save-error" data-rights-save-error hidden aria-live="polite"></p>
        </div>

        <div class="modal__actions">
            <button type="button" class="btn btn--secondary" data-rights-cancel>
                <?= $view->e($view->t('common.rights.cancel')) ?>
            </button>
            <button type="button" class="btn btn--primary" data-rights-confirm>
                <span data-rights-confirm-label><?= $view->e($view->t('common.rights.confirm')) ?></span>
            </button>
        </div>
    </div>
</div>
