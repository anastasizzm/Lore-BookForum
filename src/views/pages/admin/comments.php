<?php
/**
 * admin/comments — таблица комментариев (постов) админки.
 *
 * Строки рисует public/assets/js/admin/features/comments/list.js:
 *   GET    /api/posts?q=…&page=N&ps=20   (поиск — только по Enter)
 *   DELETE /api/posts/{id}
 * Первая страница берётся из блока data-admin-data="comments" (если контроллер
 * передал $comments / $posts / $items), иначе JS сам сходит в API.
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};
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

<p class="admin-toolbar__status" data-comments-status role="status" aria-live="polite"><?= $tx('common.admin.comments_loading', 'Loading comments…') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-comments-search
         enterkeyhint="search"
         placeholder="<?= $tr('common.admin.search_comments_ph') ?>"
         aria-label="<?= $tr('common.admin.search_comments_ph') ?>"
         autocomplete="off">
  <span class="admin-toolbar__count" data-comments-count>0</span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="comments" data-comments-table>
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
    <tbody data-comments-rows></tbody>
  </table>
</div>

<div class="admin-users__more">
  <button type="button" class="btn btn--secondary" data-comments-more hidden>
    <?= $tx('common.admin.users_load_more', 'Show more') ?>
  </button>
</div>

<?php
$adminBoot = static function (string $name, mixed $data): void {
    if ($data === null || $data === []) return;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($json)) return;
    echo '<script type="application/json" data-admin-data="' . $name . '">' . $json . '</script>' . "\n";
};
$adminBoot('comments', $comments ?? $posts ?? $items ?? null);
?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script type="module" src="<?= $view->asset('js/admin/main.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>