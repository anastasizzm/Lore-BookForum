<?php
/**
 * Ожидаемые переменные от контроллера:
 *   $book — объект App\Models\Publications\Book
 *           (id, title, createdAt, iconId, creator, genre + опционально: rating, savesCount,
 *            isbn, annotation, authorNote, series, category, tableOfContents, isSaved, readingStatus)
 *   $comments — массив постов (те же поля, что в feed-list.php)
 *
 * Поля, которых может не быть в модели, читаются через ?? — чтобы страница не падала.
 * Объекты (genre, category, series) приводятся к строке через хелпер $str — чтобы
 * не ловить "Object of class BasicModel could not be converted to string".
 */

$book = $book ?? null;
if ($book === null) {
    return;
}

/* ---------- Универсальное приведение к строке ---------- */
$str = static function ($v): string {
    if ($v === null) return '';
    if (is_string($v)) return $v;
    if (is_scalar($v)) return (string) $v;
    if (is_object($v)) {
        if (method_exists($v, '__toString')) return (string) $v;
        if (isset($v->title)) return (string) $v->title;
        if (isset($v->name))  return (string) $v->name;
    }
    return '';
};

/* ---------- Скалярные значения ---------- */

$publicationId = (int)    ($book->id ?? 0);
$bookId        = (int)    ($book->id ?? 0);
$isSaved       = (bool)   ($book->isSaved       ?? false);
$readingStatus = (string) ($book->readingStatus ?? 'new');
$rating        = (float)  ($book->rating        ?? 0);
$savesCount    = (int)    ($book->savesCount    ?? 0);

$percent = number_format(max(0, min(100, $rating / 5 * 100)), 2, '.', '');

/* ---------- Даты ---------- */

$createdAt = '';
if (($book->createdAt ?? null) instanceof \DateTimeInterface) {
    $createdAt = $book->createdAt->format('d.m.Y');
} elseif (is_string($book->createdAt ?? null)) {
    $createdAt = $book->createdAt;
}

/* ---------- Автор ---------- */

$authorName = '';
if (($book->creator ?? null) !== null) {
    $authorName = trim(($book->creator->name ?? '') . ' ' . ($book->creator->surname ?? ''));
    if ($authorName === '') {
        $authorName = (string) ($book->creator->username ?? '');
    }
}

/* ---------- Жанр / категория / серия / ISBN ---------- */

$genreTitle    = $str($book->genre    ?? null);
$categoryTitle = $str($book->category ?? null);
$seriesTitle   = $str($book->series   ?? null);
$isbn          = $str($book->isbn     ?? null);

/* ---------- Аннотация / заметка автора / TOC ---------- */

$annotation      = (string) ($book->annotation      ?? '');
$authorNote      = (string) ($book->authorNote      ?? '');
$tableOfContents = (array)  ($book->tableOfContents ?? []);

/* ---------- Обложка ---------- */

$coverUrl = 'https://placehold.co/400x560?text=Cover';
if (!empty($book->iconId)) {
    if ($book->iconId instanceof \Stringable) {
        $coverUrl = '/uploads/covers/' . (string) $book->iconId;
    } elseif (is_string($book->iconId)) {
        $coverUrl = '/uploads/covers/' . $book->iconId;
    }
}

/* ---------- Статус чтения -> кнопка ---------- */

$readingButtons = [
    'new'         => ['label' => 'Start reading',  'modifier' => 'start'],
    'in_progress' => ['label' => 'Resume reading', 'modifier' => 'resume'],
    'finished'    => ['label' => 'Read again',     'modifier' => 'again'],
];
if (!isset($readingButtons[$readingStatus])) {
    $readingStatus = 'new';
}
$readingBtn = $readingButtons[$readingStatus];
?>
<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'library'); ?>

<?php $view->startBlock('title'); ?>Book details<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/book.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <div class="book-page-header">
    <h1 class="book-page-title">Book details</h1>
  </div>

  <div class="book-details">
    <div class="book-details__cover-col">
      <div class="book-details__cover">
        <img src="<?= $view->e($coverUrl) ?>" alt="<?= $view->e($book->title ?? '') ?> cover">
      </div>

      <div class="book-actions">
        <button type="button"
                class="btn-icon btn-icon--circle<?= $isSaved ? ' is-active' : '' ?>"
                data-save-book
                data-book-id="<?= $bookId ?>"
                data-save-url="/api/books/<?= $bookId ?>/save"
                aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                aria-label="<?= $isSaved ? 'Remove from saved' : 'Save book' ?>">
          <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </button>
        <button type="button"
                class="btn btn--primary btn--pill btn--read btn--read-<?= $view->e($readingBtn['modifier']) ?>"
                data-start-reading
                data-reading-status="<?= $view->e($readingStatus) ?>"><?= $view->e($readingBtn['label']) ?></button>
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

      <div class="rate" data-rate role="radiogroup" aria-label="Rate this book">
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
      <h2 class="info-box__title"><?= $view->e($book->title ?? '') ?></h2>
      <p class="info-box__meta"><?= $view->e($authorName) ?></p>
      <p class="info-box__meta">Creation date: <?= $view->e($createdAt) ?></p>
      <?php if ($genreTitle !== ''): ?>
        <p class="info-box__meta">Genre: <?= $view->e($genreTitle) ?></p>
      <?php endif; ?>
      <?php if ($categoryTitle !== ''): ?>
        <p class="info-box__meta">Category: <?= $view->e($categoryTitle) ?></p>
      <?php endif; ?>
      <?php if ($seriesTitle !== ''): ?>
        <p class="info-box__meta">Book series: <?= $view->e($seriesTitle) ?></p>
      <?php endif; ?>
      <?php if ($isbn !== ''): ?>
        <p class="info-box__meta">ISBN: <?= $view->e($isbn) ?></p>
      <?php endif; ?>

      <div class="book-tabs-panel">
        <?php
          $view->include('tabs', [
              'variant' => 'outline',
              'items'   => [
                  ['label' => 'Annotation', 'href' => '#annotation', 'active' => true, 'row' => 'annotation'],
                  ['label' => 'Table of contents', 'href' => '#toc', 'active' => false, 'row' => 'toc'],
              ],
          ]);
        ?>

        <div class="card-base book-tabs-panel__content" data-row="annotation">
          <?php if ($annotation !== ''): ?>
            <p class="info-box__body"><?= nl2br($view->e($annotation)) ?></p>
          <?php else: ?>
            <p class="info-box__body">Annotation is not available yet.</p>
          <?php endif; ?>

          <?php if ($authorNote !== ''): ?>
            <div class="book-tabs-panel__note">
              <strong>Author's Note:</strong><br>
              <?= nl2br($view->e($authorNote)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card-base book-tabs-panel__content" data-row="toc" hidden>
          <?php if (empty($tableOfContents)): ?>
            <p class="info-box__body">Table of contents is not available yet.</p>
          <?php else: ?>
            <ol class="info-box__body">
              <?php foreach ($tableOfContents as $chapter): ?>
                <li><?= $view->e($chapter) ?></li>
              <?php endforeach; ?>
            </ol>
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