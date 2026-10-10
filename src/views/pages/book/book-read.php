<?php
/**
 * book-read — страница чтения книги (PDF.js).
 *
 * Регистрируется closure-роутом в config/routes.php:
 *   GET /books/{bookId}/read  ->  Response::html(View::render('book/book-read', ...))
 *
 * Ожидаемые переменные (все опциональны, читаются через ??):
 *   $bookId — id книги (для ссылки «назад»),
 *   $book   — объект книги (title и т.п.), если контроллер его передаст,
 *   $pdfUrl — адрес PDF-файла; по умолчанию /api/books/{id}/file
 *             (маршрут должен появиться на бэке, см. задачу для бэка)
 *
 * Логика — public/assets/js/book-read.js (модуль, PDF.js лежит в js/pdfjs/).
 */

$bookId = (int) ($bookId ?? 0);
$book   = $book ?? null;
$title  = is_object($book)
    ? (string) ($book->title ?? '')
    : (string) ($book['title'] ?? '');

$pdfUrl  = (string) ($pdfUrl ?? ($bookId ? '/api/books/' . $bookId . '/file' : ''));
$backUrl = $bookId ? '/books/' . $bookId : '/books';
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

<section class="reader"
         data-reader
         data-book-id="<?= $bookId ?>"
         data-pdf-url="<?= $view->e($pdfUrl) ?>">

  <div class="reader__toolbar">
    <button type="button" class="reader__btn" data-reader-prev aria-label="Previous page" disabled>
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Prev</span>
    </button>

    <div class="reader__toolbar-group">
      <button type="button" class="reader__btn" data-reader-zoom-out aria-label="Zoom out">−</button>
      <span class="reader__page">
        Page
        <input class="reader__page-num" type="number" min="1" value="1"
               data-reader-page aria-label="Page number" style="width:4ch;text-align:center">
        / <span data-reader-total>–</span>
      </span>
      <button type="button" class="reader__btn" data-reader-zoom-in aria-label="Zoom in">+</button>
      <button type="button" class="reader__btn" data-reader-zoom-reset
              aria-label="Reset zoom" data-reader-zoom-label>100%</button>
    </div>

    <button type="button" class="reader__btn" data-reader-next aria-label="Next page" disabled>
      <span>Next</span>
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>

  <p class="reader__status" data-reader-status>Loading…</p>

  <div class="reader__canvas-wrap" data-reader-stage>
    <canvas class="reader__canvas" width="816" height="1056" data-reader-canvas
            aria-label="<?= $title !== '' ? $view->e($title) : 'Book page' ?>">
      Your browser does not support the canvas element.
    </canvas>
  </div>

  <p><a href="<?= $view->e($backUrl) ?>">&larr; Back to the book</a></p>

</section>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script type="module" src="<?= $view->asset('js/book-read.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>
