<?php
/**
 * card-draft — карточка черновика (книга или статья в статусе draft).
 *
 * Страница — src/views/pages/drafts/drafts.php (DR-02/DR-03/DR-08).
 *
 * @param int    $id      — id публикации
 * @param string $type    — 'book' | 'article' (определяет ссылки ниже)
 * @param string $title   — название
 * @param string $cover   — адрес обложки или '' (тогда CSS-заглушка .cover--empty)
 * @param string $date    — уже отформатированная дата изменения (д.m.Y)
 *
 * ССЫЛКИ (роуты books.edit / articles.edit в config/routes.php нет —
 * роуты заморожены, поэтому правка живёт на существующем роуте
 * создания через query-параметр):
 *   Продолжить → /books/create?id={id}             (book-form, edit-режим)
 *                /articles/create?id={id}&part=meta (article-edit-meta)
 *   Открыть    → /books/{id}      или /articles/{id}
 *
 * ДЕЙСТВИЯ (API уже существует в config/endpoints.php):
 *   Опубликовать → POST   /api/{type}s/{id}/publish  (UpdateMetaPublicationOperation)
 *   Удалить      → DELETE /api/{type}s/{id}          (DeletePublicationOperation)
 *   Обрабатывает app.js (блок «Drafts: publish / delete»), делегирование по
 *   [data-draft-publish] / [data-draft-delete] внутри [data-draft-id].
 *
 * Кнопки вместо ссылки-обёртки всего карточки (в отличие от card-book):
 * у черновика два взаимоисключающих действия, поэтому клик по обложке
 * или заголовку ведёт на «Открыть», а не на редактирование.
 */
$id    = (int) ($id ?? 0);
$type  = ($type ?? 'book') === 'article' ? 'article' : 'book';
$title = (string) ($title ?? '');
$cover = (string) ($cover ?? '');
$date  = (string) ($date ?? '');

$isArticle = $type === 'article';
$basePath  = $isArticle ? '/articles/' : '/books/';

$openUrl = $id > 0 ? $basePath . $id : '#';
// Роутов edit нет (см. шапку) — раньше «Продолжить» давал 404. Правка
// размещена на роуте создания: книга — book-form.php в edit-режиме
// (?id=N: префилл из JSON-блоба + PATCH /api/books/{id}), статья —
// article-edit-meta.php (пока только разметка, без префилла и отправки).
$editUrl = $id > 0
    ? ($isArticle ? '/articles/create?id=' . $id . '&part=meta' : '/books/create?id=' . $id)
    : '#';

$hasCover = $cover !== '';

$typeLabel = $view->t($isArticle ? 'common.drafts.type_article' : 'common.drafts.type_book');

// API-URL действий: собираются из $type и $id, чтобы JS не знал про типы.
$apiBase    = '/api/' . ($isArticle ? 'articles' : 'books') . '/' . $id;
$publishUrl = $id > 0 ? $apiBase . '/publish' : '';
$deleteUrl  = $id > 0 ? $apiBase : '';
?>
<article class="card-base card-draft<?= $hasCover ? '' : ' card-draft--no-cover' ?>"
         data-draft-type="<?= $view->e($type) ?>"
         data-draft-id="<?= $id ?>">

  <div class="card-draft__cover<?= $hasCover ? '' : ' cover--empty' ?>">
    <?php if ($hasCover): ?>
      <img src="<?= $view->e($cover) ?>"
           alt="<?= $view->e($title) ?>"
           loading="lazy">
    <?php endif; ?>
    <!-- Бейдж статуса поверх обложки: черновик виден сразу -->
    <span class="card-draft__badge"><?= $view->e($view->t('common.drafts.badge')) ?></span>
  </div>

  <h3 class="card-draft__title">
    <a class="card-draft__link" href="<?= $view->e($openUrl) ?>">
      <?= $view->e($title) ?>
    </a>
  </h3>

  <p class="card-draft__meta">
    <span class="card-draft__type"><?= $view->e($typeLabel) ?></span>
    <?php if ($date !== ''): ?>
      <span class="card-draft__sep" aria-hidden="true">·</span>
      <time class="card-draft__date"><?= $view->e($view->t('common.drafts.updated', ['value' => $date])) ?></time>
    <?php endif; ?>
  </p>

  <div class="card-draft__actions">
    <a class="btn btn--secondary card-draft__edit"
       href="<?= $view->e($editUrl) ?>"
       aria-label="<?= $view->e($view->t('common.drafts.continue') . ': ' . $title) ?>">
      <?= $view->e($view->t('common.drafts.continue')) ?>
    </a>
    <a class="card-draft__open"
       href="<?= $view->e($openUrl) ?>"
       aria-label="<?= $view->e($view->t('common.drafts.open') . ': ' . $title) ?>">
      <?= $view->e($view->t('common.drafts.open')) ?>
    </a>
  </div>

  <?php if ($id > 0): ?>
  <div class="card-draft__manage">
    <button type="button"
            class="btn btn--primary btn--sm card-draft__publish"
            data-draft-publish
            data-draft-url="<?= $view->e($publishUrl) ?>"
            aria-label="<?= $view->e($view->t('common.drafts.publish') . ': ' . $title) ?>">
      <?= $view->e($view->t('common.drafts.publish')) ?>
    </button>
    <button type="button"
            class="btn btn--danger btn--sm card-draft__delete"
            data-draft-delete
            data-draft-url="<?= $view->e($deleteUrl) ?>"
            data-confirm="<?= $view->e($view->t('common.drafts.delete_confirm', ['title' => $title])) ?>"
            aria-label="<?= $view->e($view->t('common.drafts.delete') . ': ' . $title) ?>">
      <?= $view->e($view->t('common.drafts.delete')) ?>
    </button>
  </div>
  <?php endif; ?>

</article>