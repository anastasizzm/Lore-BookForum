<?php
/**
 * card-book — вертикальная карточка книги.
 *
 * Ожидает:
 *   $id       — ID книги
 *   $cover    — URL обложки
 *   $title    — название книги
 *   $authorId — ID автора
 *   $author   — имя автора
 */
$id       = $id       ?? 0;
$cover    = $cover    ?? '';
$title    = $title    ?? '';
$authorId = $authorId ?? 0;
$author   = $author   ?? '';

// TODO: заменить, когда будут маршруты
$bookUrl   = '#';
$authorUrl = '#';
// $bookUrl   = $view->url('book',   ['id' => $id]);
// $authorUrl = $view->url('author', ['id' => $authorId]);
?>
<article class="card-base card-book">

  <div class="card-book__cover">
    <img src="<?= $view->e($cover) ?>" alt="<?= $view->e($title) ?>">
  </div>

  <h3 class="card-book__title">
    <a class="card-book__link" href="<?= $view->e($bookUrl) ?>">
      <?= $view->e($title) ?>
    </a>
  </h3>

  <p class="card-book__author">
    <a class="card-book__author-link" href="<?= $view->e($authorUrl) ?>">
      <?= $view->e($author) ?>
    </a>
  </p>

</article>