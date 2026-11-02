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
 * ПРАВА (управляются permissions.js, window.LorePermissions):
 *   Пункты «Изменить» (карандаш) и Delete рендерятся скрытыми (hidden).
 *   permissions.js проверяет data-requires="publication.edit" / "publication.delete",
 *   учитывая data-requires-kind (book/article) и data-requires-owner.
 *   Если права нет — пункт остаётся скрытым. Если есть — показывается.
 *
 * КАРАДАШ «ИЗМЕНИТЬ» (спека): вместо прежней ссылки Edit — кнопка с
 * карандашом, открывающая подменю из трёх пунктов:
 *   1. Метаданные   — /{books|articles}/{id}/edit#step-1;
 *   2. Содержание   — /{books|articles}/{id}/edit#step-2;
 *   3. Роли         — открывает publish-диалог с модулем ролей (mode:'roles'),
 *                     гасится permissions.js по data-requires="publication.manageRoles";
 *   4. Пользователи — открывает модуль на экране 2 (пользователи публикации)
 *                     (data-requires="publication.managePeople").
 * Подменю раскрывает details-menu.js (data-edit-toggle / data-edit-submenu).
 *
 * Удаление выполняет details-menu.js (DELETE /api/books/{id},
 * /api/articles/{id}); ошибки показывает Messages.
 *
 * ПУНКТ «Пожаловаться» — открывает общую плашку report-modal.php
 * (report-modal.js, window.ReportModal). Он НЕ имеет data-requires:
 * жалобу может оставить любой авторизованный пользователь, в том числе
 * автор публикации, поэтому permissions.js его не трогает.
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

/** Карандаш: инлайн-SVG для пункта «Изменить». */
$pencilSvg = '<svg class="details-menu__pencil" width="15" height="15" viewBox="0 0 16 16" fill="none" '
    . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . '<path d="M11.5 2.5l2 2L6 12l-3 1 1-3z"/></svg>';
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
    
    <!-- Группа «Изменить»: карандаш открывает подменю (мета / содержание / роли) -->
    <div class="details-menu__group" data-edit-group>
      <button type="button"
              class="details-menu__item details-menu__item--parent"
              data-edit-toggle
              data-requires="publication.edit"
              data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
              data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
              aria-haspopup="true"
              aria-expanded="false"
              hidden>
        <?= $pencilSvg ?>
        <?= $view->e($view->t('common.roles.change')) ?>
      </button>

      <div class="details-menu__submenu" data-edit-submenu hidden>
        <!-- 1. Метаданные: шаг 1 формы -->
        <a class="details-menu__item"
           href="<?= $view->e($editHref) ?>#step-1">
          <?= $view->e($view->t('common.roles.edit_meta')) ?>
        </a>

        <!-- 2. Содержание: шаг 2 формы -->
        <a class="details-menu__item"
           href="<?= $view->e($editHref) ?>#step-2">
          <?= $view->e($view->t('common.roles.edit_content')) ?>
        </a>

        <!-- 3. Роли: открывает publish-диалог с модулем ролей (mode:'roles').
             data-requires="publication.manageRoles" — permissions.js скрывает
             пункт, если права нет. -->
        <button type="button"
                class="details-menu__item"
                data-roles-open
                data-requires="publication.manageRoles"
                data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
                data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
                hidden
                data-pub-type="<?= $view->e($type) ?>"
                data-pub-id="<?= $id ?>">
          <?= $view->e($view->t('common.roles.edit_roles')) ?>
        </button>

        <!-- 4. Пользователи: открывает модуль на втором экране (пользователи
             публикации) — экран 2 финального потока создания/редактирования. -->
        <button type="button"
                class="details-menu__item"
                data-roles-open-users
                data-requires="publication.managePeople"
                data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
                data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
                hidden
                data-pub-type="<?= $view->e($type) ?>"
                data-pub-id="<?= $id ?>">
          <?= $view->e($view->t('common.roles.edit_users')) ?>
        </button>
      </div>
    </div>

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

    <!-- Пункт «Пожаловаться»: без data-requires (доступен всем,
         permissions.js его не трогает), открывает report-modal.php
         через report-modal.js. -->
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