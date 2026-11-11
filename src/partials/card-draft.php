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
 * Карточка целиком ведёт в редактирование. Роутов edit нет (они
 * заморожены), поэтому правка живёт на роуте создания через ?id=N:
 *   /books/create?id={id}#step-1      (book-form, edit-режим)
 *   /articles/create?id={id}#step-1   (article-form, edit-режим)
 *
 * Publish / Delete намеренно не рендерятся здесь: они находятся на экране
 * редактирования (book-form / article-form), куда ведёт клик по карточке.
 * Ссылка заголовка растянута на всю карточку (::after в drafts.css).
 */
$id    = (int) ($id ?? 0);
$type  = ($type ?? 'book') === 'article' ? 'article' : 'book';
$title = (string) ($title ?? '');
$cover = (string) ($cover ?? '');
$date  = (string) ($date ?? '');

$isArticle = $type === 'article';

$lang = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : '');

$editUrl = '#';
if ($id > 0) {
    $editUrl = ($isArticle ? '/articles/create?id=' : '/books/create?id=') . $id . '#step-1';
    if ($lang !== '') {
        $editUrl .= (str_contains($editUrl, '?') ? '&' : '?') . 'lang=' . urlencode($lang);
        // Хэш должен оставаться последним: переносим его в конец.
        $editUrl = str_replace('#step-1', '', $editUrl) . '#step-1';
    }
}

$hasCover = $cover !== '';

$typeLabel = $view->t($isArticle ? 'common.drafts.type_article' : 'common.drafts.type_book');
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
    <a class="card-draft__link"
       href="<?= $view->e($editUrl) ?>"
       aria-label="<?= $view->e($view->t('common.drafts.continue') . ': ' . $title) ?>">
      <?= $view->e($title) ?>
    </a>
  </h3>

  <p class="card-draft__meta">
    <span class="card-draft__type"><?= $view->e($typeLabel) ?></span>
    <?php if ($date !== ''): ?>
      <span class="card-draft__sep" aria-hidden="true">·</span>
      <time class="card-draft__date"><?= $view->e($date) ?></time>
    <?php endif; ?>
  </p>

</article>
