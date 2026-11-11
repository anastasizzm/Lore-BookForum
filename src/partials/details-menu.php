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
 * СТРУКТУРА (спека): дропдаун ПЛОСКИЙ, без подменю — одна кнопка
 * редактирования (полная форма) плюс роли/пользователи и Delete:
 *   1. Редактировать           — /{books|articles}/create?id=N
 *                               (полная форма в edit-режиме);
 *   2. Изменить роли          — publish-диалог с модулем ролей (mode:'roles'),
 *                               data-requires="publication.manageRoles";
 *   3. Изменить пользователей — модуль на втором экране (пользователи
 *                               публикации), data-requires="publication.managePeople";
 *   4. Delete                 — details-menu.js (DELETE /api/books|articles/{id}).
 * Пункты 1–3 гасит permissions.js; подменю больше нет — details-menu.js
 * его логику не содержит.
 *
 * Удаление выполняет details-menu.js (DELETE /api/books/{id},
 * /api/articles/{id}); ошибки показывает Messages.
 */
$type     = $type ?? 'book';
$id       = (int) ($id ?? 0);
$isOwner  = !empty($isOwner);

$isBook    = $type === 'book';
// Веб-роута /{books|articles}/{id}/edit в config/routes.php нет (роуты
// заморожены): правка живёт на роуте создания (?id=N), полная форма
// открывается одним экраном (в edit-режиме шаги не разделяются).
$editHref = ($isBook ? '/books/create?id=' : '/articles/create?id=') . $id;
$deleteUrl = ($isBook ? '/api/books/' : '/api/articles/') . $id;
$listHref  = $isBook ? '/books' : '/articles';
$ariaLabel = $view->t($isBook ? 'common.book.options' : 'common.article.options');
$editLabel = $isBook ? $view->t('common.book.edit') : $view->t('common.roles.edit');
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

    <!-- 1. Редактирование: полная форма (в edit-режиме все поля на одном
         экране, без шагов «Далее»). data-requires="publication.edit" —
         permissions.js скрывает пункт, если нет ни updateMeta, ни updateContent
         (владелец получает право через data-requires-owner). -->
    <a class="details-menu__item"
       href="<?= $view->e($editHref) ?>"
       data-requires="publication.edit"
       data-requires-kind="<?= $isBook ? 'book' : 'article' ?>"
       data-requires-owner="<?= $isOwner ? '1' : '0' ?>"
       hidden>
      <?= $view->e($editLabel) ?>
    </a>

    <!-- 2. Роли: открывает publish-диалог с модулем ролей (mode:'roles').
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

    <!-- 3. Пользователи: открывает модуль на втором экране (пользователи
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
  </div>
</div>