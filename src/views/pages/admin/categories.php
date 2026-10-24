<?php
/**
 * admin/categories — таблица справочника «Категории» (каркас, мок-данные).
 *
 * Структура записи и поведение — как в admin/genres.php:
 * массив translations (расширяемый на любой язык), клиентский поиск по всем
 * переводам, add/edit/delete — через fetch-слой dictApi в admin.js
 * (пока роут /api/admin/categories нет — режим mock, правки локальные).
 *
 * Тест: GET /pages/test?page=admin/categories
 *
 * Стили — public/assets/css/admin.css.
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- МОК-строки: сюда придёт GET /api/admin/categories (dictApi в admin.js) ----
$categories = [
    ['id' => 1, 'translations' => [
        ['langCode' => 'en', 'title' => 'Novel'],
        ['langCode' => 'ru', 'title' => 'Роман'],
        ['langCode' => 'es', 'title' => 'Novela'],
    ]],
    ['id' => 2, 'translations' => [
        ['langCode' => 'en', 'title' => 'Short story'],
        ['langCode' => 'ru', 'title' => 'Рассказ'],
    ]],
    ['id' => 3, 'translations' => [
        ['langCode' => 'en', 'title' => 'Non-fiction'],
        ['langCode' => 'ru', 'title' => 'Документальная литература'],
    ]],
    ['id' => 4, 'translations' => [
        ['langCode' => 'ru', 'title' => 'Антология'],
        ['langCode' => 'en', 'title' => 'Anthology'],
    ]],
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.categories_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.categories_title'),
    'subtitle' => $view->t('common.admin.categories_subtitle'),
]); ?>

<!-- Прячется admin.js, когда GET /api/admin/categories реально отвечает -->
<p class="admin-note" data-dict-stub><?= $tr('common.admin.stub_note') ?></p>

<?php $view->include('dictionary-table', [
    'resource' => 'categories',
    'items'    => $categories,
    'searchPh' => $view->t('common.admin.search_dict_ph'),
    'addLabel' => $view->t('common.admin.action_add'),
]); ?>

<?php $view->include('dictionary-form', [
    'resource' => 'categories',
    'nameOne'  => $view->t('common.admin.category_one'),
]); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
