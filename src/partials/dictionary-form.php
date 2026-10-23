<?php
/**
 * dictionary-form — модалка «Добавить / Редактировать» для справочников
 * (Genres / Categories / Types). Одна на все три ресурса: различие только
 * в data-dict-resource (путь /api/admin/{resource}) и в имени сущности.
 *
 * @param string $resource — 'genres' | 'categories' | 'types'
 * @param string $nameOne  — локализованное имя сущности («Genre» / «Жанр»),
 *                           подставляется в заголовок модалки
 *
 * Тело формы — блок Translations:
 *   строка [код языка] [input Title] [✕] на каждый добавленный язык,
 *   кнопка «+ Add language» открывает dropdown с доступными языками
 *   (список — GET /api/additional/languages, fallback-мок в admin.js).
 *
 * Вся механика (префилл при Edit, добавление/удаление языка, валидация
 * «хотя бы один перевод», сохранение и удаление через ConfirmModal) —
 * public/assets/js/admin.js; сабмит/удаление работают по мок-массиву,
 * TODO(api) внутри.
 *
 * Стили — public/assets/css/admin.css (.admin-dict__*, каркас .modal —
 * modal.css, поля — .form-field из component.css, выбор языка —
 * существующий partial-класс .dropdown).
 */
$resource = $resource ?? 'genres';
$nameOne  = $nameOne ?? '';
?>
<div class="modal admin-dict"
     data-dict-modal
     hidden
     data-dict-resource="<?= $view->e($resource) ?>"
     data-dict-name="<?= $view->e($nameOne) ?>">
    <div class="modal__backdrop" data-dict-dismiss></div>

    <div class="modal__dialog admin-dict__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="adminDictTitle">
        <h2 class="modal__title" id="adminDictTitle" data-dict-title></h2>
        <p class="modal__text admin-dict__hint"><?= $view->e($view->t('common.admin.dict_hint')) ?></p>

        <form class="admin-dict__form" data-dict-form novalidate>
            <div class="admin-dict__section">
                <span class="admin-dict__label" id="adminDictLangsLabel">
                    <?= $view->e($view->t('common.admin.col_translations')) ?>
                </span>

                <!-- Строки языков рисует admin.js (столько, сколько переводов) -->
                <ul class="admin-dict__langs" data-dict-langs aria-labelledby="adminDictLangsLabel"></ul>

                <p class="admin-dict__langs-empty" data-dict-langs-empty hidden>
                    <?= $view->e($view->t('common.admin.dict_langs_empty')) ?>
                </p>

                <!-- Выбор языка — существующий dropdown (component.css + app.js):
                     контейнер есть в исходной разметке, поэтому app.js управляет
                     открытием/закрытием; пункты наполняет admin.js. -->
                <div class="dropdown admin-dict__lang" data-dropdown data-dict-lang-dropdown>
                    <button type="button" class="dropdown__trigger" data-dropdown-trigger>
                        <span><?= $view->e($view->t('common.admin.dict_add_lang')) ?></span>
                        <span class="dropdown__arrow" aria-hidden="true">&#9662;</span>
                    </button>
                    <ul class="dropdown__menu admin-dict__lang-menu" data-dict-lang-menu></ul>
                </div>

                <p class="admin-dict__lang-status" data-dict-lang-status hidden aria-live="polite"></p>
            </div>

            <!-- Ошибка валидации («хотя бы один перевод») -->
            <p class="form-field__error admin-dict__error" data-dict-error hidden role="alert"></p>

            <div class="modal__actions">
                <button type="button" class="btn btn--secondary" data-dict-cancel>
                    <?= $view->e($view->t('common.cancel')) ?>
                </button>
                <button type="submit" class="btn btn--primary" data-dict-save>
                    <?= $view->e($view->t('common.save')) ?>
                </button>
            </div>
        </form>
    </div>
</div>
