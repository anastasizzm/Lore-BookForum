<?php
/**
 * admin/books — каркас таблицы книг админки.
 *
 * Статус: ЗАГЛОВКА без проверок прав (имена разрешений от бэка ждём)
 * и без запросов к API — API админки ещё нет.
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-toolbar).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: строки таблицы. Придут из GET /api/admin/books ----
$books = [
    ['id' => 1, 'title' => 'The Silent Library',   'author' => 'anastasizzm', 'genre' => 'Fantasy',  'created' => '2026-10-01'],
    ['id' => 2, 'title' => 'Notes on Reading',     'author' => 'reader_one',  'genre' => 'Non-fiction', 'created' => '2026-10-05'],
    ['id' => 3, 'title' => 'Archive of Borders',   'author' => 'anastasizzm', 'genre' => 'Sci-Fi',   'created' => '2026-10-12'],
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.books_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.books_title'),
    'subtitle' => $view->t('common.admin.books_subtitle'),
]); ?>

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         placeholder="<?= $tr('common.admin.search_books_ph') ?>"
         aria-label="<?= $tr('common.admin.search_books_ph') ?>"
         autocomplete="off">
  <span class="admin-toolbar__count"><?= count($books) ?></span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="books">
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_title') ?></th>
        <th scope="col"><?= $tr('common.admin.col_author') ?></th>
        <th scope="col"><?= $tr('common.admin.col_genre') ?></th>
        <th scope="col"><?= $tr('common.admin.col_created') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($books as $bookRow): $bookRow['id'] = (int) $bookRow['id']; ?>
        <tr data-book-id="<?= $bookRow['id'] ?>">
          <td class="admin-table__id"><?= $bookRow['id'] ?></td>
          <td><span class="admin-table__clip" title="<?= $view->e($bookRow['title']) ?>"><?= $view->e($bookRow['title']) ?></span></td>
          <td><?= $view->e($bookRow['author']) ?></td>
          <td><span class="admin-badge"><?= $view->e($bookRow['genre']) ?></span></td>
          <td><?= $view->e($bookRow['created']) ?></td>
          <td>
            <div class="admin-actions">
              <!-- TODO(роуты): url('books.retrieve', ['bookId' => $bookRow['id']]) -->
              <a class="btn btn--secondary" href="/books/<?= $bookRow['id'] ?>"><?= $tr('common.admin.action_view') ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$books): ?>
        <tr class="admin-table__empty">
          <td colspan="6"><?= $tr('common.admin.empty') ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php $view->endBlock('content'); ?>
