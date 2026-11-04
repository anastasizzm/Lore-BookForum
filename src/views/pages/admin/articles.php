<?php
/**
 * admin/articles — таблица статей админки.
 *
 * Строки рисует public/assets/js/admin/features/articles/list.js:
 *   GET    /api/articles?include=creator&page=N&q=…
 *   DELETE /api/articles/{id}
 * Поиск уходит в API только по Enter (и при очистке поля крестиком),
 * «Показать ещё» подгружает следующую страницу.
 *
 * Тип статьи — articles.type ('content' — отдельная статья,
 * 'book' — отрывок из книги; см. storage/db/schema.sql).
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-toolbar,
 * .admin-badge, .admin-actions).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.articles_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('admin-back'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.articles_title'),
    'subtitle' => $view->t('common.admin.articles_subtitle'),
]); ?>

<p class="admin-toolbar__status" data-articles-status role="status" aria-live="polite"><?= $tx('common.admin.articles_loading', 'Loading articles…') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-articles-search
         placeholder="<?= $tr('common.admin.search_articles_ph') ?>"
         aria-label="<?= $tr('common.admin.search_articles_ph') ?>"
         enterkeyhint="search"
         autocomplete="off">
  <span class="admin-toolbar__count" data-articles-count>0</span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="articles" data-articles-table>
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
    <tbody data-articles-rows></tbody>
  </table>
</div>

<div class="admin-users__more">
  <button type="button" class="btn btn--secondary" data-articles-more hidden>
    <?= $tx('common.admin.users_load_more', 'Show more') ?>
  </button>
</div>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script type="module" src="<?= $view->asset('js/admin/main.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>