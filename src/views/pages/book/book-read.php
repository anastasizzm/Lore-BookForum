<?php
/**
 * book-read — страница чтения книги (чтение с экрана).
 *
 * Намеренно БЕЗ JavaScript: только разметка и CSS.
 *   - .reader__toolbar — панель управления: Prev / Next / Zoom− / Zoom+ /
 *     номер страницы (как ссылки/кнопки без обработчиков — вёрстка под
 *     будущую логику чтения);
 *   - .reader__canvas-wrap + <canvas> — область страницы.
 *
 * Регистрируется closure-роутом в config/routes.php:
 *   GET /books/{bookId}/read  ->  Response::html(View::render('book/book-read', ...))
 *
 * Ожидаемые переменные (все опциональны, читаются через ??):
 *   $bookId — id книги (для подписи/ссылки «назад»),
 *   $book   — объект книги (title и т.п.), если контроллер его передаст,
 *   $page   — текущая страница (по умолчанию 1),
 *   $pages  — всего страниц (по умолчанию 1)
 */

$bookId = (int) ($bookId ?? 0);
$book   = $book ?? null;
$title  = is_object($book)
    ? (string) ($book->title ?? '')
    : (string) ($book['title'] ?? '');

$page  = max(1, (int) ($page ?? 1));
$pages = max($page, (int) ($pages ?? 1));
?>

<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'books'); ?>

<?php $view->startBlock('title'); ?>
  <?= $title !== '' ? $view->e($title) . ' — ' : '' ?>Read — Book App
<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/book.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<section class="reader" data-book-id="<?= $bookId ?>">

  <!-- Панель управления: prev / zoom− / страница / zoom+ / next (без JS) -->
  <div class="reader__toolbar">
    <a class="reader__btn" href="<?= $bookId ? '/books/' . $bookId : '/books' ?>"
       rel="prev" aria-label="Previous page">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Prev</span>
    </a>

    <div class="reader__toolbar-group">
      <button type="button" class="reader__btn" aria-label="Zoom out">−</button>
      <span class="reader__page">
        Page <span class="reader__page-num"><?= $page ?></span>
        / <?= $pages ?>
      </span>
      <button type="button" class="reader__btn" aria-label="Zoom in">+</button>
    </div>

    <a class="reader__btn" href="<?= $bookId ? '/books/' . $bookId : '/books' ?>"
       rel="next" aria-label="Next page">
      <span>Next</span>
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </a>
  </div>

  <!-- Область страницы: canvas рисуется без JS-скриптов страницы -->
  <div class="reader__canvas-wrap">
    <canvas class="reader__canvas" width="816" height="1056"
            aria-label="<?= $title !== '' ? $view->e($title) : 'Book page' ?>">
      Your browser does not support the canvas element.
    </canvas>
  </div>

</section>

<?php $view->endBlock('content'); ?>
