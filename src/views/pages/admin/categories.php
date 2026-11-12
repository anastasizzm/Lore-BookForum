<?php
/**
 * admin/categories — таблица справочника «Категории» (каркас, мок-данные).
 *
 * Структура записи и поведение — как в admin/genres.php:
 * массив translations (расширяемый на любой язык), клиентский поиск по всем
 * переводам, add/edit/delete — через fetch-слой dictApi в admin/main.js
 * (пока роут /api/admin/categories нет — режим mock, правки локальные).
 *
 * Тест: GET /pages/test?page=admin/categories
 *
 * Стили — public/assets/css/admin.css.
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// Данные контроллера (если он их передал) — до того, как переменную перезапишет мок ниже
$serverItems = $categories ?? $items ?? null;

?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.categories_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('admin-back'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.categories_title'),
    'subtitle' => $view->t('common.admin.categories_subtitle'),
]); ?>

<div class="admin-toolbar">
  <!-- form="dictSearchDetached" (формы с таким id нет): поле не отправляется обычным submit-ом, если страница обёрнута в <form> -->
  <input class="admin-toolbar__search"
         type="search"
         form="dictSearchDetached"
         data-dict-search
         placeholder="<?= $view->e($view->t('common.admin.search_dict_ph')) ?>"
         aria-label="<?= $view->e($view->t('common.admin.search_dict_ph')) ?>"
         enterkeyhint="search"
         autocomplete="off">

  <button type="button"
          class="btn btn--primary admin-toolbar__add admin-toolbar__add--icon"
          data-admin-dict-add
          title="<?= $view->e($view->t('common.admin.action_add')) ?>"
          aria-label="<?= $view->e($view->t('common.admin.action_add')) ?>">
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="9" y1="3" x2="9" y2="15"></line>
      <line x1="3" y1="9" x2="15" y2="9"></line>
    </svg>
  </button>

  <span class="admin-toolbar__count" data-dict-count>0</span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table" data-dict-table data-dict-resource="categories">
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_title') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
      </tr>
    </thead>
    <tbody data-dict-rows>
      <tr class="admin-table__empty" data-admin-empty hidden>
        <td colspan="3"><?= $tr('common.admin.empty') ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Служебные данные для admin/main.js (ресурс; строки приходят из данных страницы / API) -->
<script type="application/json" data-dict-data>{"resource":"categories","items":[]}</script>

<?php $view->include('dictionary-form', [
    'resource' => 'categories',
    'nameOne'  => $view->t('common.admin.category_one'),
]); ?>

<?php
/*
 * Данные страницы уже есть у контроллера — отдаём их в разметку, чтобы первая
 * загрузка таблицы не делала запрос к API (JS читает блок один раз и удаляет;
 * поиск, фильтры и «Показать ещё» дальше идут через API как раньше).
 * Нет данных / не сериализуются — блок не выводится, JS сам сходит в API.
 */
$adminBoot = static function (string $name, mixed $data): void {
    if ($data === null || $data === []) return;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($json)) return;
    echo '<script type="application/json" data-admin-data="' . $name . '">' . $json . '</script>' . "\n";
};
$adminBoot('dictionary', $serverItems);
?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script type="module" src="<?= $view->asset('js/admin/main.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>