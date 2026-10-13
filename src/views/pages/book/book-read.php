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
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php $view->setBlock('selectedTab', 'library'); ?>

<?php $view->startBlock('title'); ?>
  <?= $title !== '' ? $view->e($title) . ' — ' : '' ?><?= $tr('common.reader.title') ?> — <?= $tr('common.common.app_name') ?>
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

    <!-- Страницы: назад / номер из всего / вперёд -->
    <div class="reader__pager" role="group" aria-label="<?= $tr('common.reader.pager') ?>">
      <button type="button" class="reader__btn" data-reader-prev aria-label="<?= $tr('common.reader.prev_aria') ?>" disabled>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span><?= $tr('common.reader.prev') ?></span>
      </button>

      <span class="reader__page">
        <?= $tr('common.reader.page') ?>
        <input class="reader__page-num" type="number" min="1" value="1"
               data-reader-page aria-label="<?= $tr('common.reader.page_aria') ?>">
        / <span data-reader-total>–</span>
      </span>

      <button type="button" class="reader__btn" data-reader-next aria-label="<?= $tr('common.reader.next_aria') ?>" disabled>
        <span><?= $tr('common.reader.next') ?></span>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <!-- Масштаб: отдельная группа -->
    <div class="reader__zoom" role="group" aria-label="<?= $tr('common.reader.zoom') ?>">
      <button type="button" class="reader__btn" data-reader-zoom-out aria-label="<?= $tr('common.reader.zoom_out') ?>">−</button>
      <button type="button" class="reader__btn reader__zoom-value" data-reader-zoom-reset
              aria-label="<?= $tr('common.reader.zoom_reset') ?>" data-reader-zoom-label>100%</button>
      <button type="button" class="reader__btn" data-reader-zoom-in aria-label="<?= $tr('common.reader.zoom_in') ?>">+</button>
    </div>

  </div>

  <p class="reader__status" data-reader-status><?= $tr('common.reader.loading') ?></p>

  <div class="reader__canvas-wrap" data-reader-stage>
    <canvas class="reader__canvas" width="816" height="1056" data-reader-canvas
            aria-label="<?= $title !== '' ? $view->e($title) : $tr('common.reader.canvas_label') ?>">
      <?= $tr('common.reader.canvas_fallback') ?>
    </canvas>
  </div>

  <p><a href="<?= $view->e($backUrl) ?>">&larr; <?= $tr('common.reader.back') ?></a></p>

</section>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script type="module" src="<?= $view->asset('js/book-read.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>