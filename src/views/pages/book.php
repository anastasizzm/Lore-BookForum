<?php
/**
 * Ожидаемые переменные от контроллера:
 *   $book      — ['cover','title','author','createdAt','genre','category','series',
 *                 'rating' (float 0-5), 'savesCount', 'annotation', 'authorNote', 'tableOfContents']
 *   $comments  — массив постов для card-feed.php (те же поля, что в feed-list.php),
 *                но БЕЗ книжного блока — передаём withBook = false
 *
 * Ниже — временные заглушки, чтобы страницу можно было проверить до готовности бэка.
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

$fullStars  = (int) floor($book['rating']);
$emptyStars = 5 - $fullStars;
?>
<?php $view->extends('main'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/book.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <div class="book-page-header">
    <h1 class="book-page-header__title page-header__title">Book details</h1>
    <div class="book-page-header__actions">
      <button type="button" class="btn-icon" aria-label="Save book" data-toggle-bookmark>
        <svg width="18" height="18" viewBox="0 0 17 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M2 2.5C2 1.83696 2.21401 1.20107 2.59494 0.732233C2.97587 0.263392 3.49253 0 4.03125 0L12.1562 0C12.695 0 13.2116 0.263392 13.5926 0.732233C13.9735 1.20107 14.1875 1.83696 14.1875 2.5V19.375C14.1875 19.4881 14.1625 19.599 14.1153 19.6959C14.0681 19.7929 14.0004 19.8723 13.9194 19.9257C13.8384 19.979 13.7472 20.0044 13.6554 19.999C13.5637 19.9936 13.4748 19.9576 13.3984 19.895L8.09375 16.3762L2.78914 19.895C2.71267 19.9576 2.62383 19.9936 2.53208 19.999C2.44033 20.0044 2.3491 19.979 2.26812 19.9257C2.18714 19.8723 2.11944 19.7929 2.07223 19.6959C2.02501 19.599 2.00005 19.4881 2 19.375V2.5ZM4.03125 1.25C3.76189 1.25 3.50356 1.3817 3.31309 1.61612C3.12263 1.85054 3.01562 2.16848 3.01562 2.5V18.2075L7.81242 15.105C7.89576 15.0367 7.99364 15.0003 8.09375 15.0003C8.19386 15.0003 8.29174 15.0367 8.37508 15.105L13.1719 18.2075V2.5C13.1719 2.16848 13.0649 1.85054 12.8744 1.61612C12.6839 1.3817 12.4256 1.25 12.1562 1.25H4.03125Z" fill="currentColor"/>
        </svg>
      </button>
      <button type="button" class="btn btn--primary" data-start-reading>Start reading</button>
    </div>
  </div>

  <div class="book-details">
    <div class="book-details__cover-col">
      <div class="book-details__cover">
        <img src="<?= $view->e($book['cover']) ?>" alt="<?= $view->e($book['title']) ?> cover">
      </div>

      <div class="book-rating">
        <span class="book-rating__stars" aria-hidden="true">
          <?= str_repeat('★', $fullStars) ?><span class="is-empty"><?= str_repeat('☆', $emptyStars) ?></span>
        </span>
        <span class="book-rating__value"><?= $view->e(number_format($book['rating'], 1)) ?></span>
        <span class="book-rating__saves">
          🔖 <?= (int) $book['savesCount'] ?>
        </span>
      </div>
    </div>

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
              <strong>Author's Note:</strong>
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

  <section class="comments-section">
    <h2 class="comments-section__title">
      Comments - <span class="comments-section__count"><?= count($comments) ?></span>
    </h2>

    <form class="comment-composer" action="/comments" method="POST">
      <?= $view->csrfField() ?>
      <?php
        $size = 'sm';
        $initials = 'ME';
        $src = null;
        $view->include('avatar', ['size' => $size, 'initials' => $initials, 'src' => $src]);
      ?>
      <div class="input">
        <input class="input__field" type="text" name="text" placeholder="Input comments...">
      </div>
    </form>

    <div class="stack">
      <?php foreach ($comments as $comment): ?>
        <?php
          $view->include('card-feed', array_merge($comment, ['withBook' => false]));
        ?>
      <?php endforeach; ?>
    </div>
  </section>

<?php $view->endBlock('content'); ?>
