<?php
/**
 * details-menu — меню «три точки» в шапке страницы публикации
 * (book-details / article-details): редактирование и удаление.
 *
 * ПРАВА (управляются permissions.js):
 *   Пункты Edit и Delete рендерятся скрытыми (hidden).
 *   permissions.js проверяет data-requires="publication.edit" / "publication.delete",
 *   учитывая data-requires-kind (book/article) и data-requires-owner.
 *   Если права нет — пункт остается скрытым. Если есть — показывается.
 */
$type    = $type ?? 'book';
$id      = (int) ($id ?? 0);
$isOwner = !empty($isOwner);

$isBook    = $type === 'book';
$editHref  = ($isBook ? '/books/' : '/articles/') . $id . '/edit';
$deleteUrl = ($isBook ? '/api/books/' : '/api/articles/') . $id;
$listHref  = $isBook ? '/books' : '/articles';
$ariaLabel = $view->t($isBook ? 'common.book.options' : 'common.article.options');
?>
<div class="details-menu"
     data-details-menu-wrap
     data-pub-type="<?= $view->e($type) ?>">
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
    
    <!-- Кнопка Edit -->
    <a class="details-menu__item"
       href="<?= $view->e($editHref) ?>"
       data-edit-btn
       data-requires="publication.edit"
       data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
       data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
       hidden
       <?= $isBook
            ? 'data-book-id="' . $id . '"'
            : 'data-pub-id="' . $id . '"' ?>>
      <?= $view->e($view->t('common.book.edit')) ?>
    </a>

    <!-- Кнопка Delete -->
    <button type="button"
            class="details-menu__item details-menu__item--danger"
            data-details-delete
            data-requires="publication.delete"
            data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
            data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
            hidden
            data-delete-url="<?= $view->e($deleteUrl) ?>"
            data-delete-redirect="<?= $view->e($listHref) ?>"
            data-delete-kind="<?= $view->e($type) ?>">
      <?= $view->e($view->t('common.js.delete')) ?>
    </button>
  </div>
</div>