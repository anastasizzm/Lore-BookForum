<?php
/**
 * admin/genres — таблица справочника «Жанры» (каркас, мок-данные).
 *
 * Статус: ЗАГЛУШКА без проверок прав и без запросов к API админки —
 * сущности лежат в локальном мок-массиве, add/edit/delete в admin.js
 * работают по нему же (TODO(api) внутри).
 *
 * Структура записи — массив переводов, а не объект с фиксированными ключами,
 * поэтому новый язык не требует изменений схемы:
 *   ['id' => 1, 'translations' => [['langCode' => 'en', 'title' => 'Fantasy'],
 *                                  ['langCode' => 'ru', 'title' => 'Фэнтези']]]
 *
 * Поиск — клиентский, по всем переводам сразу (data-search у строки).
 * Таблица/тулбар — partial dictionary-table; модалка формы — dictionary-form.
 *
 * Тест: GET /pages/test?page=admin/genres
 *
 * Стили — public/assets/css/admin.css (.admin-toolbar, .admin-table,
 * .admin-dict__*).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: строки таблицы. Придут из GET /api/admin/genres ----
$genres = [
    ['id' => 1, 'translations' => [
        ['langCode' => 'en', 'title' => 'Fantasy'],
        ['langCode' => 'ru', 'title' => 'Фэнтези'],
        ['langCode' => 'es', 'title' => 'Fantasía'],
    ]],
    ['id' => 2, 'translations' => [
        ['langCode' => 'en', 'title' => 'Detective'],
        ['langCode' => 'ru', 'title' => 'Детектив'],
    ]],
    ['id' => 3, 'translations' => [
        ['langCode' => 'en', 'title' => 'Science Fiction'],
        ['langCode' => 'ru', 'title' => 'Научная фантастика'],
        ['langCode' => 'es', 'title' => 'Ciencia ficción'],
    ]],
    ['id' => 4, 'translations' => [
        ['langCode' => 'en', 'title' => 'Poetry'],
        ['langCode' => 'ru', 'title' => 'Поэзия'],
    ]],
    ['id' => 5, 'translations' => [
        ['langCode' => 'ru', 'title' => 'Фольклор'],
    ]],
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.genres_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.genres_title'),
    'subtitle' => $view->t('common.admin.genres_subtitle'),
]); ?>

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<?php $view->include('dictionary-table', [
    'resource' => 'genres',
    'items'    => $genres,
    'searchPh' => $view->t('common.admin.search_dict_ph'),
    'addLabel' => $view->t('common.admin.action_add'),
]); ?>

<?php $view->include('dictionary-form', [
    'resource' => 'genres',
    'nameOne'  => $view->t('common.admin.genre_one'),
]); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
