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
 *   Каждый пункт рендерится скрытым (hidden) и проверяется по своему
 *   data-requires: publication.updateMeta / publication.updateContent /
 *   publication.manageRoles / publication.managePeople / publication.delete,
 *   с учётом data-requires-kind (book/article) и data-requires-owner.
 *   Если права нет — пункт остаётся скрытым. Если есть — показывается.
 *
 * СТРУКТУРА (спека): дропдаун ПЛОСКИЙ, без подменю — 4 кнопки правки
 * плюс Delete, каждый пункт со своим правом:
 *   1. Изменить мета-данные   — /{books|articles}/create?id=N#step-1
 *                               (полная форма в edit-режиме, шаг 1);
 *   2. Изменить контент       — /{books|articles}/create?id=N#step-2
 *                               (полная форма в edit-режиме, шаг 2);
 *   3. Изменить роли          — publish-диалог с модулем ролей (mode:'roles'),
 *                               data-requires="publication.manageRoles";
 *   4. Изменить пользователей — модуль на втором экране (пользователи
 *                               публикации), data-requires="publication.managePeople";
 *   5. Delete                 — details-menu.js (DELETE /api/books|articles/{id});
 *   6. Пожаловаться           — report-modal, без data-requires.
 * Пункты 1–4 гасит permissions.js; подменю больше нет — details-menu.js
 * его логику не содержит.
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
// Веб-роута /{books|articles}/{id}/edit в config/routes.php нет (роуты
// заморожены): правка живёт на роуте создания (?id=N), а hash открывает
// нужный шаг полной формы. Обе формы уже умеют префилл и сохранение.
$metaHref    = ($isBook ? '/books/create?id=' : '/articles/create?id=') . $id . '#step-1';
$contentHref = ($isBook ? '/books/create?id=' : '/articles/create?id=') . $id . '#step-2';
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

    <!-- 1. Метаданные: полная форма правки, шаг 1
         (/books/create?id=N#step-1 / /articles/create?id=N#step-1).
         data-requires="publication.updateMeta" — permissions.js скрывает
         пункт, если права нет. -->
    <a class="details-menu__item"
       href="<?= $view->e($metaHref) ?>"
       data-requires="publication.updateMeta"
       data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
       data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
       hidden>
      <?= $view->e($view->t('common.roles.edit_meta')) ?>
    </a>

    <!-- 2. Контент: полная форма правки, шаг 2
         (/books/create?id=N#step-2 / /articles/create?id=N#step-2). -->
    <a class="details-menu__item"
       href="<?= $view->e($contentHref) ?>"
       data-requires="publication.updateContent"
       data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
       data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
       hidden>
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