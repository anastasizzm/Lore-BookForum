<?php
/**
 * admin/types — таблица справочника «Типы» (каркас, мок-данные).
 *
 * Структура записи и поведение — как в admin/genres.php:
 * массив translations (расширяемый на любой язык), клиентский поиск по всем
 * переводам, add/edit/delete на мок-массиве (TODO(api) в admin.js).
 *
 * Тест: GET /pages/test?page=admin/types
 *
 * Стили — public/assets/css/admin.css.
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: строки таблицы. Придут из GET /api/admin/types ----
$types = [
    ['id' => 1, 'translations' => [
        ['langCode' => 'en', 'title' => 'Book'],
        ['langCode' => 'ru', 'title' => 'Книга'],
        ['langCode' => 'es', 'title' => 'Libro'],
    ]],
    ['id' => 2, 'translations' => [
        ['langCode' => 'en', 'title' => 'Article'],
        ['langCode' => 'ru', 'title' => 'Статья'],
    ]],
    ['id' => 3, 'translations' => [
        ['langCode' => 'en', 'title' => 'Book excerpt'],
        ['langCode' => 'ru', 'title' => 'Отрывок из книги'],
    ]],
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.types_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.types_title'),
    'subtitle' => $view->t('common.admin.types_subtitle'),
]); ?>

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<?php $view->include('dictionary-table', [
    'resource' => 'types',
    'items'    => $types,
    'searchPh' => $view->t('common.admin.search_dict_ph'),
    'addLabel' => $view->t('common.admin.action_add'),
]); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
