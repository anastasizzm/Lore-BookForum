<?php
/**
 * admin/comments — каркас таблицы комментариев админки.
 *
 * Статус: ЗАГЛОВКА без проверок прав (имена разрешений от бэка ждём)
 * и без запросов к API — API админки ещё нет.
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-toolbar,
 * .admin-actions — кнопки модерации).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: строки таблицы. Придут из GET /api/admin/comments ----
$comments = [
    ['id' => 1, 'author' => 'reader_one',    'text' => 'The excerpt from chapter 3 is exactly what I needed.', 'publication' => 'Chapter 3 excerpt', 'created' => '2026-10-13'],
    ['id' => 2, 'author' => 'anastasizzm',   'text' => 'Thanks! Updated the annotation.',                       'publication' => 'The Silent Library', 'created' => '2026-10-13'],
    ['id' => 3, 'author' => 'spam_bot',      'text' => 'Check out my channel!!!',                               'publication' => 'Notes on Reading',   'created' => '2026-10-14'],
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.comments_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.comments_title'),
    'subtitle' => $view->t('common.admin.comments_subtitle'),
]); ?>

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         placeholder="<?= $tr('common.admin.search_comments_ph') ?>"
         aria-label="<?= $tr('common.admin.search_comments_ph') ?>"
         autocomplete="off">
  <span class="admin-toolbar__count"><?= count($comments) ?></span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="comments">
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_author') ?></th>
        <th scope="col"><?= $tr('common.admin.col_text') ?></th>
        <th scope="col"><?= $tr('common.admin.col_publication') ?></th>
        <th scope="col"><?= $tr('common.admin.col_created') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($comments as $commentRow): $commentRow['id'] = (int) $commentRow['id']; ?>
        <tr data-comment-id="<?= $commentRow['id'] ?>">
          <td class="admin-table__id"><?= $commentRow['id'] ?></td>
          <td><?= $view->e($commentRow['author']) ?></td>
          <td><span class="admin-table__clip" title="<?= $view->e($commentRow['text']) ?>"><?= $view->e($commentRow['text']) ?></span></td>
          <td><span class="admin-table__clip" title="<?= $view->e($commentRow['publication']) ?>"><?= $view->e($commentRow['publication']) ?></span></td>
          <td><?= $view->e($commentRow['created']) ?></td>
          <td>
            <div class="admin-actions">
              <!-- TODO(api): удаление/скрытие комментария — API админки ещё нет,
                   проверок прав не добавляем (имена разрешений ждём от бэка). -->
              <button type="button"
                      class="btn btn--danger"
                      data-admin-delete
                      data-comment-id="<?= $commentRow['id'] ?>">
                <?= $tr('common.admin.action_delete') ?>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$comments): ?>
        <tr class="admin-table__empty">
          <td colspan="6"><?= $tr('common.admin.empty') ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
