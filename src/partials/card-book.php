<?php
/**
 * card-book — вертикальная карточка книги.
 *
 * Ожидает:
 *   $id     — ID книги (для ссылки)
 *   $cover  — URL обложки
 *   $title  — название книги
 *   $author — автор
 */
$id     = $id     ?? 0;
$cover  = $cover  ?? '';
$title  = $title  ?? '';
$author = $author ?? '';
?>
<a class="card-base card-book" href="#">
  <div class="card-book__cover">
    <img src="<?= $view->e($cover) ?>" alt="<?= $view->e($title) ?>">
  </div>

  <h3 class="card-book__title"><?= $view->e($title) ?></h3>
  <p class="card-book__author"><?= $view->e($author) ?></p>
</a>