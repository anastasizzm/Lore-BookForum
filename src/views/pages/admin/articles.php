<?php
/**
 * admin/articles — каркас таблицы статей админки.
 *
 * Статус: ЗАГЛОВКА без проверок прав (имена разрешений от бэка ждём)
 * и без запросов к API — API админки ещё нет.
 *
 * Тип статьи — колонка articles.type ('content' — отдельная статья,
 * 'book' — отрывок из книги; см. storage/db/schema.sql).
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-toolbar).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: строки таблицы. Придут из GET /api/admin/articles ----
$articles = [
    ['id' => 1, 'title' => 'How to read slowly',   'type' => 'content', 'author' => 'anastasizzm', 'created' => '2026-10-02'],
    ['id' => 2, 'title' => 'Chapter 3 excerpt',    'type' => 'book',    'author' => 'reader_one',  'created' => '2026-10-06'],
    ['id' => 3, 'title' => 'Margins and memory',   'type' => 'content', 'author' => 'reader_one',  'created' => '2026-10-11'],
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.articles_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.articles_title'),
    'subtitle' => $view->t('common.admin.articles_subtitle'),
]); ?>

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         placeholder="<?= $tr('common.admin.search_articles_ph') ?>"
         aria-label="<?= $tr('common.admin.search_articles_ph') ?>"
         autocomplete="off">
  <span class="admin-toolbar__count"><?= count($articles) ?></span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="articles">
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_title') ?></th>
        <th scope="col"><?= $tr('common.admin.col_type') ?></th>
        <th scope="col"><?= $tr('common.admin.col_author') ?></th>
        <th scope="col"><?= $tr('common.admin.col_created') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($articles as $articleRow): $articleRow['id'] = (int) $articleRow['id']; ?>
        <tr data-article-id="<?= $articleRow['id'] ?>">
          <td class="admin-table__id"><?= $articleRow['id'] ?></td>
          <td><span class="admin-table__clip" title="<?= $view->e($articleRow['title']) ?>"><?= $view->e($articleRow['title']) ?></span></td>
          <td>
            <?php if ($articleRow['type'] === 'book'): ?>
              <span class="admin-badge admin-badge--accent"><?= $tr('common.admin.type_excerpt') ?></span>
            <?php else: ?>
              <span class="admin-badge"><?= $tr('common.admin.type_standalone') ?></span>
            <?php endif; ?>
          </td>
          <td><?= $view->e($articleRow['author']) ?></td>
          <td><?= $view->e($articleRow['created']) ?></td>
          <td>
            <div class="admin-actions">
              <!-- TODO(роуты): url('articles.retrieve', ['articleId' => $articleRow['id']]) -->
              <a class="btn btn--secondary" href="/articles/<?= $articleRow['id'] ?>"><?= $tr('common.admin.action_view') ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$articles): ?>
        <tr class="admin-table__empty">
          <td colspan="6"><?= $tr('common.admin.empty') ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php $view->endBlock('content'); ?>
