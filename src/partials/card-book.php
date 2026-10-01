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
 *   $saved    — сохранена ли книга текущим пользователем (bool)
 */

$id       = (int)($id       ?? 0);
$cover    = (string)($cover    ?? '');
$title    = (string)($title    ?? '');
$authorId = (int)($authorId ?? 0);
$author   = (string)($author   ?? '');
$saved    = (bool)($saved    ?? false);

// ИСПРАВЛЕНО: Правильный путь к заглушке через папку /assets
$coverSrc = $cover !== '' ? $cover : '/assets/img/book-placeholder.svg';

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

    <!-- Закладка: POST/DELETE /api/books/{id}/save (обработчик в app.js) -->
    <button type="button"
            class="btn-icon btn-icon--circle card-book__save<?= $saved ? ' is-active' : '' ?>"
            data-save-book
            data-book-id="<?= $id ?>"
            aria-pressed="<?= $saved ? 'true' : 'false' ?>"
            aria-label="<?= $saved ? 'Remove from saved' : 'Save book' ?>">
      <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
      </svg>
    </button>
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