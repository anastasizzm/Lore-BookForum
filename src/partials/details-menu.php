<?php
/**
 * details-menu — меню «три точки» в шапке страницы публикации
 * (book-details / article-details): редактирование и удаление.
 *
 * Три точки стоят СПРАВА В ШАПКЕ, на одной линии с заголовком
 * «Book/Article details»; кнопка «Редактировать» переехала сюда из
 * тела страницы.
 *
 * @param string $type    - 'book' | 'article'
 * @param int    $id      - id публикации
 * @param bool   $isOwner - текущий пользователь — автор/владелец публикации
 * @param string $title   - название публикации (подпись цели жалобы в
 *                          плашке «Пожаловаться»; необязательно)
 *
 * Три точки открываются на глобальных обработчиках details-menu.js
 * (тот же приём «открыть/закрыть/Escape/клик мимо», что у меню
 * комментариев в comments.js).
 *
 * ПРАВА (скрываем/показываем кнопки Edit и Delete):
 *   book-rights.js (данные — window.LoreRules, public/assets/js/rules.js)
 *   дёргает GET /api/books/rules или GET /api/articles/rules и включает
 *   пункты, подтверждённые правилами updateMeta/updateContent (Edit)
 *   и delete (Delete).
 *   Глобальные правила НЕ знают про владельца, поэтому $isOwner кладётся
 *   в data-rules-owner и складывается с правилами на фронте.
 *   Пункты рендерятся СЕРЫМИ (aria-disabled=true); если права не пришли —
 *   остаются серыми (не показываем кнопки, вернущие 403).
 *
 * КОНТРАКТ (бэк добавит роуты; config/ и app/ заморожены):
 *   GET    /books/{id}/edit, /articles/{id}/edit — страницы редактирования
 *          (формы book-form.php / article-form.php уже есть)
 *   DELETE /api/books/{id}     → 204; 401 — нет сессии, 419 — CSRF,
 *          403/404 — нет прав или книга уже удалена
 *   DELETE /api/articles/{id}  → 204, та же семантика
 *   Удаление выполняет details-menu.js; ошибки показывает Messages.
 *
 * ПУНКТ «Пожаловаться» — открывает общую плашку report-modal.php
 * (report-modal.js, window.ReportModal). Он НЕ имеет data-rule: жалобу
 * может оставить любой авторизованный пользователь, в том числе автор
 * публикации, поэтому book-rights.js его не гасит.
 */
$type     = $type ?? 'book';
$id       = (int) ($id ?? 0);
$isOwner  = !empty($isOwner);
$title    = (string) ($title ?? '');

$isBook    = $type === 'book';
$editHref  = ($isBook ? '/books/' : '/articles/') . $id . '/edit';
$deleteUrl = ($isBook ? '/api/books/' : '/api/articles/') . $id;
$listHref  = $isBook ? '/books' : '/articles';
$ariaLabel = $view->t($isBook ? 'common.book.options' : 'common.article.options');
?>
<div class="details-menu"
     data-details-menu-wrap
     data-pub-type="<?= $view->e($type) ?>"
     data-rules-kind="<?= $isBook ? 'books' : 'articles' ?>"
     data-rules-owner="<?= $isOwner ? '1' : '0' ?>">
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
    <!-- Пункт «Редактировать»: data-rule="edit" включает book-rights.js
         по правилам updateMeta || updateContent (владелец — всегда). -->
    <a class="details-menu__item is-disabled"
       href="<?= $view->e($editHref) ?>"
       data-edit-btn
       data-rule="edit"
       aria-disabled="true"
       tabindex="-1"
       <?= $isBook
            ? 'data-book-id="' . $id . '"'
            : 'data-pub-id="' . $id . '"' ?>>
      <?= $view->e($view->t('common.book.edit')) ?>
    </a>

    <!-- Пункт «Удалить»: data-rule="delete" (правило delete), дальше
         details-menu.js → ConfirmModal → DELETE. -->
    <button type="button"
            class="details-menu__item details-menu__item--danger is-disabled"
            data-details-delete
            data-rule="delete"
            aria-disabled="true"
            tabindex="-1"
            data-delete-url="<?= $view->e($deleteUrl) ?>"
            data-delete-redirect="<?= $view->e($listHref) ?>"
            data-delete-kind="<?= $view->e($type) ?>"><?= $view->e($view->t('common.js.delete')) ?></button>

    <!-- Пункт «Пожаловаться»: без data-rule (доступен всем, book-rights.js
         его не трогает), открывает report-modal.php через report-modal.js. -->
    <button type="button"
            class="details-menu__item"
            data-report-open
            data-report-kind="<?= $view->e($type) ?>"
            data-report-id="<?= $id ?>"
            <?= $title !== '' ? 'data-report-label="' . $view->e($title) . '"' : '' ?>>
      <?= $view->e($view->t('common.report.menu')) ?>
    </button>
  </div>
</div>
