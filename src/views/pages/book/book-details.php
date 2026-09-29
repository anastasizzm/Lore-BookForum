<?php
/**
 * Ожидаемые переменные от контроллера:
 *   $book      — ['cover','title','author','createdAt','genre','category','series',
 *                 'rating' (float 0-5), 'savesCount', 'annotation', 'authorNote', 'tableOfContents']
 *   $comments  — массив постов (те же поля, что в feed-list.php)
 */
$book = $book ?? [
    'cover'           => 'https://placehold.co/400x560?text=Cover',
    'title'           => 'Full name of book',
    'author'          => 'Name Surname',
    'createdAt'       => '10.09.2026',
    'genre'           => 'Drama',
    'category'        => 'Artistic literature',
    'series'          => '10.09.2026',
    'rating'          => 4.0,
    'savesCount'      => 121,
    'annotation'      => "After the destruction of most of humanity, Grigory takes up a profession that never existed before: taxidermist of extraterrestrial fauna.\nDo you want a stuffed \"Root-Jumper\" from a distant star system, or perhaps one of the very last Glass Serpents? Nothing is impossible; Grigory will fulfill your request.\nThe job seems simple enough-until each new order begins to pull him deeper and deeper into the alien cosmos...",
    'authorNote'      => 'By the way, the series has a standalone prequel; you can read it here: https://author.today/work/627498',
    'tableOfContents' => [],
];

$comments = $comments ?? [
    [
        'userInitials' => 'SN',
        'userName'     => 'sername',
        'userAvatar'   => null,
        'date'         => '10.09.2026',
        'text'         => "some text about life and many more things some text about life and many more things some text about life and many more things\nsome text about life and many more things some text about life and many more things some text about life and many more things\nsome text about life and many more things",
        'likes'        => 0,
        'comments'     => 0,
    ],
];

$rating  = (float) ($book['rating'] ?? 0);
$percent = number_format(max(0, min(100, $rating / 5 * 100)), 2, '.', '');
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
        <img src="<?= $view->e($book['cover']) ?>" alt="<?= $view->e($book['title']) ?> cover">
      </div>

      <!-- Кнопки под обложкой (растянуты по ширине) -->
      <div class="book-actions">
        <button type="button" class="btn-icon btn-icon--circle" aria-label="Save book" data-toggle-bookmark>
          <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </button>
        <button type="button" class="btn btn--primary btn--pill" data-start-reading>Start reading</button>
      </div>

      <!-- Блок рейтинга и сохранений -->
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
          <?= (int) $book['savesCount'] ?>
        </span>
      </div>
    </div>

    <!-- Инфо-карточка книги -->
    <div class="card-base info-box">
      <h2 class="info-box__title"><?= $view->e($book['title']) ?></h2>
      <p class="info-box__meta"><?= $view->e($book['author']) ?></p>
      <p class="info-box__meta">Creation date: <?= $view->e($book['createdAt']) ?></p>
      <p class="info-box__meta">Genre: <?= $view->e($book['genre']) ?></p>
      <p class="info-box__meta">Category: <?= $view->e($book['category']) ?></p>
      <p class="info-box__meta">Book series: <?= $view->e($book['series']) ?></p>

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
          <p class="info-box__body"><?= nl2br($view->e($book['annotation'])) ?></p>

          <?php if (!empty($book['authorNote'])): ?>
            <div class="book-tabs-panel__note">
              <strong>Author's Note:</strong><br>
              <?= nl2br($view->e($book['authorNote'])) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card-base book-tabs-panel__content" data-row="toc" hidden>
          <?php if (empty($book['tableOfContents'])): ?>
            <p class="info-box__body">Table of contents is not available yet.</p>
          <?php else: ?>
            <ol class="info-box__body">
              <?php foreach ($book['tableOfContents'] as $chapter): ?>
                <li><?= $view->e($chapter) ?></li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Блок комментариев -->
  <section class="comments-section">
    <h2 class="comments-section__title">
      Comments: <?= (int) ($totalComments ?? count($comments)) ?>
    </h2>

    <!-- Форма написания комментария (стилизована как карточка) -->
    <form class="comment-card" action="#" method="POST">
      <?= $view->csrfField() ?>
      <div class="comment-card__inner">
        <?php $view->include('avatar', ['size' => 'sm', 'initials' => 'ME', 'src' => null]); ?>
        <div class="comment-card__content">
          <div class="comment-card__author">sername</div>
          <input class="comment-card__input" type="text" name="text"
                 value="<?= $view->e($form['text'] ?? '') ?>"
                 placeholder="Input comments...">
          <?php foreach (($errors['text'] ?? []) as $err): ?>
            <p class="form-field__error" style="color: red; margin-top: 8px; font-size: 14px;"><?= $view->e($err) ?></p>
          <?php endforeach; ?>
        </div>
      </div>
    </form>

    <?php if (empty($comments)): ?>
      <p class="comments-section__empty">Be the first to comment.</p>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($comments as $comment): ?>
          
          <!-- Карточка комментария -->
          <div class="comment-card">
            <div class="comment-card__inner">
              <?php $view->include('avatar', ['size' => 'sm', 'initials' => $comment['userInitials'] ?? 'SN', 'src' => $comment['userAvatar'] ?? null]); ?>
              
              <div class="comment-card__content">
                <div class="comment-card__author"><?= $view->e($comment['userName']) ?></div>
                <div class="comment-card__text"><?= nl2br($view->e($comment['text'])) ?></div>
                
                <div class="comment-card__footer">
                  <button type="button" class="btn-icon-small" aria-label="Like">
                    <svg width="15" height="14" viewBox="0 0 15 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M7.5 13.1L6.45 12.06C2.6 8.56 0 6.36 0 3.5C0 1.4 1.65 0 3.75 0C4.95 0 6.15 0.55 6.825 1.45L7.5 2.2L8.175 1.45C8.85 0.55 10.05 0 11.25 0C13.35 0 15 1.4 15 3.5C15 6.36 12.4 8.56 8.55 12.06L7.5 13.1Z" stroke="currentColor" stroke-width="1.2" fill="none"/>
                    </svg>
                  </button>
                  
                  <div class="comment-card__meta">
                    <span><?= $view->e($comment['date']) ?></span>
                    <button type="button" class="btn-icon-small" aria-label="Reply">
                      <svg width="16" height="13" viewBox="0 0 16 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5.5 1L1 5.5M1 5.5L5.5 10M1 5.5H11.5C13.9853 5.5 16 7.51472 16 10V12.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/book.js"></script>
<?php $view->endBlock('scripts'); ?>