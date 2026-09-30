<?php
/**
 * card-book — вертикальная карточка книги.
 *
 * Ожидает:
 *   $id       — ID книги
 *   $cover    — URL обложки (может быть пустым)
 *   $title    — название книги
 *   $authorId — ID автора (user_id)
 *   $author   — имя автора
 */

$id       = (int)($id       ?? 0);
$cover    = (string)($cover    ?? '');
$title    = (string)($title    ?? '');
$authorId = (int)($authorId ?? 0);
$author   = (string)($author   ?? '');

// Заглушка, если обложки нет
$coverSrc = $cover !== '' ? $cover : '/img/book-placeholder.svg';

// TODO: роут на отдельную книгу бэк ещё не добавил.
// Когда появится (например, /books/{id}) — заменить на него.
$bookUrl = '#';

// Профиль автора — реальный роут из routes.php
$authorUrl = $authorId > 0 ? '/users/' . $authorId : '#';
?>
<article class="card-base card-book">

  <div class="card-book__cover">
    <img src="<?= $view->e($coverSrc) ?>"
         alt="<?= $view->e($title) ?>"
         loading="lazy">
  </div>

  <h3 class="card-book__title">
    <a class="card-book__link"
       href="<?= $view->e($bookUrl) ?>"
       aria-label="Открыть страницу книги «<?= $view->e($title) ?>»">
      <?= $view->e($title) ?>
    </a>
  </h3>

  <?php if ($author !== ''): ?>
    <p class="card-book__author">
      <a class="card-book__author-link"
         href="<?= $view->e($authorUrl) ?>"
         aria-label="Открыть профиль автора <?= $view->e($author) ?>">
        <?= $view->e($author) ?>
      </a>
    </p>
  <?php endif; ?>

</article>