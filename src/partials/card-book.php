<?php
/**
 * card-book — вертикальная карточка книги.
 *
 * Ожидает:
 *   $cover    — URL обложки
 *   $title    — название книги
 *   $author   — автор
 *   $likes    — число лайков (опционально)
 *   $comments — число комментариев (опционально)
 */
$cover    = $cover    ?? '';
$title    = $title    ?? '';
$author   = $author   ?? '';
$likes    = $likes    ?? null;
$comments = $comments ?? null;
?>
<article class="card-base card-book">
  <div class="card-book__cover">
    <img src="<?= $view->e($cover) ?>" alt="">
  </div>

  <h3 class="card-book__title"><?= $view->e($title) ?></h3>
  <p class="card-book__author"><?= $view->e($author) ?></p>
</article>