<?php
/**
 * article-details — страница статьи (по аналогии с book/book-details.php).
 *
 * Ожидаемые переменные от контроллера:
 *   $article — объект App\Models\Publications\Article
 *   $comments — массив постов (те же поля, что в feed-list.php)
 *
 * Поля, которых может не быть в модели (annotation, authorNote, content, doi, bookTitle,
 * pageStart, pageEnd, rating, savesCount, isSaved), читаются через ?? — чтобы страница
 * не падала. Объекты (genre, creator, type) приводятся к строке через хелпер $str.
 */

$article = $article ?? null;
if ($article === null) {
    return;
}

/* ---------- Универсальное приведение к строке ---------- */
$str = static function ($v): string {
    if ($v === null) return '';
    if (is_string($v)) return $v;
    if (is_scalar($v)) return (string) $v;
    if ($v instanceof \BackedEnum) return (string) $v->value;
    if (is_object($v)) {
        if (method_exists($v, '__toString')) return (string) $v;
        if (isset($v->title)) return (string) $v->title;
        if (isset($v->name))  return (string) $v->name;
    }
    return '';
};

/* ---------- Скалярные значения ---------- */

$publicationId = (int)  ($article->id ?? 0);
$articleId     = (int)  ($article->id ?? 0);
$isSaved       = (bool) ($article->isSaved    ?? false);
$rating        = (float)($article->rating     ?? 0);
$savesCount    = (int)  ($article->savesCount ?? 0);

$percent = number_format(max(0, min(100, $rating / 5 * 100)), 2, '.', '');

/* ---------- Даты ---------- */

$createdAt = '';
if (($article->createdAt ?? null) instanceof \DateTimeInterface) {
    $createdAt = $article->createdAt->format('d.m.Y');
} elseif (is_string($article->createdAt ?? null)) {
    $createdAt = $article->createdAt;
}

/* ---------- Автор ---------- */

$authorName = '';
if (($article->creator ?? null) !== null) {
    $authorName = trim(($article->creator->name ?? '') . ' ' . ($article->creator->surname ?? ''));
    if ($authorName === '') {
        $authorName = (string) ($article->creator->username ?? '');
    }
}

/* ---------- Жанр / DOI ---------- */

$genreTitle = $str($article->genre ?? null);
$doi        = $str($article->doi   ?? null);

/* ---------- Тип статьи ---------- */
// type может быть: BackedEnum, BasicModel (объект с ->title) или строкой
$typeRaw = $article->type ?? 'content';
if ($typeRaw instanceof \BackedEnum) {
    $typeRaw = $typeRaw->value;
}
$type          = strtolower($str($typeRaw));
$isBookExcerpt = $type === 'article';

/* ---------- Аннотация / заметка / контент ---------- */

$annotation = $str($article->annotation ?? null);
$authorNote = $str($article->authorNote ?? null);
$content    = $str($article->content    ?? null);

/* ---------- Книга-источник (для type='book') ---------- */

$bookTitle = $str($article->bookTitle ?? null);

$pageStart = $article->pageStart ?? null;
$pageEnd   = $article->pageEnd   ?? ($pageStart ?? null);

/* ---------- Обложка ---------- */

$coverUrl = '/img/book-placeholder.svg';
if (!empty($article->iconId)) {
    if ($article->iconId instanceof \Stringable) {
        $coverUrl = '/uploads/covers/' . (string) $article->iconId;
    } elseif (is_string($article->iconId)) {
        $coverUrl = '/uploads/covers/' . $article->iconId;
    }
}
?>
<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'library'); ?>

<?php $view->startBlock('title'); ?>Article details<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/book.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <div class="book-page-header">
    <h1 class="book-page-title">Article details</h1>
  </div>

  <div class="book-details">
    <div class="book-details__cover-col">
      <div class="book-details__cover">
        <img src="<?= $view->e($coverUrl) ?>" alt="<?= $view->e($article->title ?? '') ?>">
      </div>

      <div class="book-actions">
        <button type="button"
                class="btn-icon btn-icon--circle<?= $isSaved ? ' is-active' : '' ?>"
                data-save-article
                data-article-id="<?= $articleId ?>"
                data-save-url="/api/articles/<?= $articleId ?>/save"
                aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                aria-label="<?= $isSaved ? 'Remove from saved' : 'Save article' ?>">
          <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </button>
        <a class="btn btn--primary btn--pill"
           href="<?= $isBookExcerpt && $bookTitle !== '' ? '#' : '#annotation' ?>">
          <?= $isBookExcerpt ? 'Open book' : 'Read article' ?>
        </a>
      </div>

      <div class="book-rating">
        <div class="book-rating__group">
          <span class="book-rating__stars"
                style="--rating-percent: <?= $view->e($percent) ?>%;"
                role="img"
                aria-label="Rating <?= $view->e(number_format($rating, 1)) ?> out of 5">
            ★★★★★
          </span>
          <span class="book-rating__value"><?= $view->e(number_format($rating, 1)) ?></span>
        </div>
        <span class="book-rating__saves">
          <svg width="12" height="16" viewBox="0 0 12 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H10C10.5523 1 11 1.44772 11 2V14.5273C11 14.928 10.5574 15.1704 10.2039 14.9631L6 12.5L1.79612 14.9631C1.44265 15.1704 1 14.928 1 14.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
          <span data-saves-count><?= $savesCount ?></span>
        </span>
      </div>

      <div class="rate" data-rate role="radiogroup" aria-label="Rate this article">
        <span class="rate__label">Click to Rate:</span>
        <div class="rate__stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button"
                    class="rate__star"
                    data-rate-value="<?= $i ?>"
                    role="radio"
                    aria-checked="false"
                    aria-label="<?= $i ?> out of 5">
              <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2.5l2.94 5.96 6.56.95-4.75 4.63 1.12 6.54L12 17.5l-5.87 3.08 1.12-6.54L2.5 9.41l6.56-.95L12 2.5z"/>
              </svg>
            </button>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="card-base info-box">
      <h2 class="info-box__title"><?= $view->e($article->title ?? '') ?></h2>
      <p class="info-box__meta"><?= $view->e($authorName) ?></p>
      <p class="info-box__meta">Creation date: <?= $view->e($createdAt) ?></p>
      <?php if ($genreTitle !== ''): ?>
        <p class="info-box__meta">Genre: <?= $view->e($genreTitle) ?></p>
      <?php endif; ?>

      <?php if ($doi !== ''): ?>
        <p class="info-box__meta">DOI: <?= $view->e($doi) ?></p>
      <?php endif; ?>

      <?php if ($isBookExcerpt): ?>
        <p class="info-box__meta">Type: Article from the book</p>
        <?php if ($bookTitle !== ''): ?>
          <p class="info-box__meta">
            Book: <?= $view->e($bookTitle) ?>
            <?php if (!empty($pageStart)): ?>
              (pp. <?= (int) $pageStart ?>–<?= (int) $pageEnd ?>)
            <?php endif; ?>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <p class="info-box__meta">Type: Standalone article</p>
      <?php endif; ?>

      <div class="book-tabs-panel">
        <?php
          $view->include('tabs', [
              'variant' => 'outline',
              'items'   => [
                  ['label' => 'Annotation', 'href' => '#annotation', 'active' => true, 'row' => 'annotation'],
                  ['label' => $isBookExcerpt ? 'Excerpt' : 'Content', 'href' => '#content', 'active' => false, 'row' => 'content'],
              ],
          ]);
        ?>

        <div class="card-base book-tabs-panel__content" data-row="annotation">
          <?php if ($annotation !== ''): ?>
            <p class="info-box__body" id="annotation"><?= nl2br($view->e($annotation)) ?></p>
          <?php else: ?>
            <p class="info-box__body" id="annotation">Annotation is not available yet.</p>
          <?php endif; ?>

          <?php if ($authorNote !== ''): ?>
            <div class="book-tabs-panel__note">
              <strong>Author's Note:</strong><br>
              <?= nl2br($view->e($authorNote)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card-base book-tabs-panel__content" data-row="content" hidden>
          <?php if ($isBookExcerpt): ?>
            <p class="info-box__body">
              The full text is taken from the book
              <strong><?= $view->e($bookTitle !== '' ? $bookTitle : 'Untitled') ?></strong><?php
                if (!empty($pageStart)):
                  ?>, pages <?= (int) $pageStart ?>–<?= (int) $pageEnd ?><?php
                endif;
              ?>.
            </p>
            <!-- TODO: route for single book is not added yet -->
            <p><a class="link" href="#">Go to the book</a></p>
          <?php elseif ($content === ''): ?>
            <p class="info-box__body">The article text is not available yet.</p>
          <?php else: ?>
            <div class="info-box__body article-content">
              <?php foreach (preg_split('/\R{2,}/u', trim($content)) as $para): ?>
                <?php if (trim($para) === '') continue; ?>
                <p><?= nl2br($view->e(trim($para))) ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <section class="comments-section">
    <h2 class="comments-section__title">
      Comments: <span data-comments-count><?= (int) ($totalComments ?? count($comments ?? [])) ?></span>
    </h2>

    <form class="comment-card" action="/api/posts" method="POST" data-comment-form novalidate>
      <?= $view->csrfField() ?>
      <input type="hidden" name="publicationId" value="<?= $publicationId ?>">
      <div class="comment-card__inner">
        <?php $view->include('avatar', ['size' => 'sm', 'initials' => 'ME', 'src' => null]); ?>
        <div class="comment-card__content">
          <div class="comment-card__author">sername</div>
          <input class="comment-card__input" type="text" name="content"
                 maxlength="2000" autocomplete="off"
                 placeholder="Input comments...">
          <p class="form-field__error" data-comment-error role="alert" hidden
             style="color: red; margin-top: 8px; font-size: 14px;"></p>
          <p data-comment-status role="status" hidden
             style="color: green; margin-top: 8px; font-size: 14px;"></p>
        </div>
      </div>
    </form>

    <?php if (empty($comments)): ?>
      <p class="comments-section__empty">Be the first to comment.</p>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($comments as $comment): ?>
          <div class="comment-card">
            <div class="comment-card__inner">
              <?php $view->include('avatar', ['size' => 'sm', 'initials' => $comment['userInitials'] ?? 'SN', 'src' => $comment['userAvatar'] ?? null]); ?>

              <div class="comment-card__content">
                <div class="comment-card__author"><?= $view->e($comment['userName']) ?></div>
                <div class="comment-card__text"><?= nl2br($view->e($comment['text'])) ?></div>

                <div class="comment-card__footer">
                  <button type="button" class="btn-icon-small btn-like" data-comment-like aria-pressed="false" aria-label="Like">
                    <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span data-comment-like-count><?= (int) ($comment['likes'] ?? 0) ?></span>
                  </button>

                  <div class="comment-card__meta">
                    <span><?= $view->e($comment['date']) ?></span>
                    <button type="button" class="btn-icon-small" data-reply-toggle aria-expanded="false" aria-label="Reply">
                      <svg width="16" height="13" viewBox="0 0 16 13" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M5.5 1L1 5.5M1 5.5L5.5 10M1 5.5H11.5C13.9853 5.5 16 7.51472 16 10V12.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <form class="comment-reply-form" data-reply-form hidden>
                  <input type="text" class="comment-reply-form__input" placeholder="Write a reply…" maxlength="500" autocomplete="off">
                  <button type="submit" class="comment-reply-form__submit" disabled aria-label="Send reply">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                </form>

                <div class="comment-replies" data-replies></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <template id="reply-template">
    <div class="comment-reply">
      <?php $view->include('avatar', ['size' => 'sm', 'initials' => 'ME', 'src' => null]); ?>
      <div class="comment-reply__content">
        <div class="comment-reply__author">sername</div>
        <div class="comment-reply__text"></div>
      </div>
    </div>
  </template>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/book.js"></script>
<?php $view->endBlock('scripts'); ?>