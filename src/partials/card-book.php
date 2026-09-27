<?php
/**
 * card-book — вертикальная карточка книги (для library и saved).
 *
 * Ожидает:
 *   $cover  — URL обложки
 *   $title  — название книги
 *   $author — автор
 *
 * Лайки и комментарии здесь НЕ показываются — они только в feed и book-details.
 */
$cover  = $cover  ?? '';
$title  = $title  ?? '';
$author = $author ?? '';
?>
<article class="card-base card-book">
  <div class="card-book__cover">
    <img src="<?= $view->e($cover) ?>" alt="<?= $view->e($title) ?>">
  </div>

  <h3 class="card-book__title"><?= $view->e($title) ?></h3>
  <p class="card-book__author"><?= $view->e($author) ?></p>
</article>