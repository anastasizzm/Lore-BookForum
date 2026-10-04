<?php
/**
 * card-book - vertical book card.
 *
 * @param string $saveType - 'book' | 'article' — куда уходит Save (/api/books|articles/{id}/save)
 */
$id       = (int)($id       ?? 0);
$cover    = (string)($cover    ?? '');
$title    = (string)($title    ?? '');
$authorId = (int)($authorId ?? 0);
$author   = (string)($author   ?? '');
$saved    = (bool)($saved    ?? false);

// Тип публикации для кнопки Save: книги и статьи сохраняются на разные эндпоинты
$saveType = ($saveType ?? 'book') === 'article' ? 'article' : 'book';
$isArticle = $saveType === 'article';
$saveAttr  = $isArticle ? 'data-save-article' : 'data-save-book';
$idAttr    = $isArticle ? 'data-article-id'   : 'data-book-id';
$saveLabel = $saved ? 'Remove from saved' : 'Save ' . $saveType;

// ИСПРАВЛЕНО: Правильный путь к заглушке через папку /assets
$coverSrc = $cover !== '' ? $cover : '/assets/img/book-placeholder.svg';

// TODO: route for single book is not added yet
$bookUrl = '#';

$authorUrl = $authorId > 0 ? '/users/' . $authorId : '#';
?>
<article class="card-base card-book">

  <div class="card-book__cover">
    <img src="<?= $view->e($coverSrc) ?>"
         alt="<?= $view->e($title) ?>"
         loading="lazy">

    <button type="button"
            class="btn-icon btn-icon--circle card-book__save<?= $saved ? ' is-active' : '' ?>"
            <?= $saveAttr ?>
            <?= $idAttr ?>="<?= $id ?>"
            data-save-url="<?= $view->e(($isArticle ? '/api/articles' : '/api/books') . '/' . $id . '/save') ?>"
            aria-pressed="<?= $saved ? 'true' : 'false' ?>"
            aria-label="<?= $view->e($saveLabel) ?>">
      <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
      </svg>
    </button>
  </div>

  <h3 class="card-book__title">
    <a class="card-book__link"
       href="<?= $view->e($bookUrl) ?>"
       aria-label="Open book: <?= $view->e($title) ?>">
      <?= $view->e($title) ?>
    </a>
  </h3>

  <?php if ($author !== ''): ?>
    <p class="card-book__author">
      <a class="card-book__author-link"
         href="<?= $view->e($authorUrl) ?>"
         aria-label="Open author profile: <?= $view->e($author) ?>">
        <?= $view->e($author) ?>
      </a>
    </p>
  <?php endif; ?>

</article>