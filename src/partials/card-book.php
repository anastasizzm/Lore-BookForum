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

// TODO: заменить на $view->url('book', ['id' => $id]), когда бэкенд добавит маршрут 'book'
// Пока — заглушка, чтобы страница рендерилась без ошибки.
$bookUrl = '#';
// $bookUrl = $view->url('book', ['id' => $id]);
?>
<a class="card-base card-book" href="<?= $view->e($bookUrl) ?>">
  <div class="card-book__cover">
    <img src="<?= $view->e($cover) ?>" alt="<?= $view->e($title) ?>">
  </div>

  <h3 class="card-book__title"><?= $view->e($title) ?></h3>
  <p class="card-book__author"><?= $view->e($author) ?></p>
</a>