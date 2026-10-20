<?php
/**
 * details-menu — меню «три точки» в шапке страницы публикации
 * (book-details / article-details): редактирование и удаление.
 *
 * Три точки стоят СПРАВА В ШАПКЕ, на одной линии с заголовком
 * «Book/Article details»; кнопка «Редактировать» переехала сюда из
 * тела страницы.
 *
 * @param string $type - 'book' | 'article'
 * @param int    $id   - id публикации
 *
 * Три точки открываются на глобальных обработчиках details-menu.js
 * (тот же приём «открыть/закрыть/Escape/клик мимо», что у меню
 * комментариев в comments.js).
 *
 * КОНТРАКТ (бэк добавит роуты; config/ и app/ заморожены):
 *   GET    /books/{id}/edit, /articles/{id}/edit — страницы редактирования
 *          (формы book-form.php / article-form.php уже есть)
 *   DELETE /api/books/{id}     → 204; 401 — нет сессии, 419 — CSRF,
 *          403/404 — нет прав или книга уже удалена
 *   DELETE /api/articles/{id}  → 204, та же семантика
 *   Права (book-details, book-rights.js): GET /api/publications/{id}/rights
 *   Удаление выполняет details-menu.js; ошибки показывает Messages.
 */
$type = $type ?? 'book';
$id   = (int) ($id ?? 0);

$isBook    = $type === 'book';
$editHref  = ($isBook ? '/books/' : '/articles/') . $id . '/edit';
$deleteUrl = ($isBook ? '/api/books/' : '/api/articles/') . $id;
$listHref  = $isBook ? '/books' : '/articles';
$ariaLabel = $view->t($isBook ? 'common.book.options' : 'common.article.options');
?>
<div class="details-menu" data-details-menu-wrap data-pub-type="<?= $view->e($type) ?>">
  <button type="button"
          class="btn-icon details-menu__toggle"
          data-details-menu-toggle
          aria-haspopup="true"
          aria-expanded="false"
          aria-label="<?= $view->e($ariaLabel) ?>">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <circle cx="8" cy="3" r="1.5"/>
      <circle cx="8" cy="8" r="1.5"/>
      <circle cx="8" cy="13" r="1.5"/>
    </svg>
  </button>

  <div class="details-menu__popup" data-details-menu hidden>
    <!-- Пункт «Редактировать»: data-edit-btn остаётся — book-rights.js
         подгружает права и красит пункт (синий — canEdit, красный —
         canPublish, серый — прав нет). -->
    <a class="details-menu__item"
       href="<?= $view->e($editHref) ?>"
       data-edit-btn
       <?= $isBook
            ? 'data-book-id="' . $id . '"'
            : 'data-pub-id="' . $id . '"' ?>
       data-rights-state="none"><?= $view->e($view->t('common.book.edit')) ?></a>

    <!-- Пункт «Удалить»: details-menu.js → ConfirmModal → DELETE.
         Права бэк проверит сам — до него фронт не дотягивается. -->
    <button type="button"
            class="details-menu__item details-menu__item--danger"
            data-details-delete
            data-delete-url="<?= $view->e($deleteUrl) ?>"
            data-delete-redirect="<?= $view->e($listHref) ?>"
            data-delete-kind="<?= $view->e($type) ?>"><?= $view->e($view->t('common.js.delete')) ?></button>
  </div>
</div>
