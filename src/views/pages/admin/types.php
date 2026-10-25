<?php
/**
 * admin/types — таблица справочника «Типы» (каркас, мок-данные).
 *
 * Структура записи и поведение — как в admin/genres.php:
 * массив translations (расширяемый на любой язык), клиентский поиск по всем
 * переводам, add/edit/delete — через fetch-слой dictApi в admin.js
 * (пока роут /api/admin/types нет — режим mock, правки локальные).
 *
 * Тест: GET /pages/test?page=admin/types
 *
 * Стили — public/assets/css/admin.css.
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- МОК-строки: сюда придёт GET /api/admin/types (dictApi в admin.js) ----
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

<!-- Прячется admin.js, когда GET /api/admin/types реально отвечает -->
<p class="admin-note" data-dict-stub><?= $tr('common.admin.stub_note') ?></p>

<?php $view->include('dictionary-table', [
    'resource' => 'types',
    'items'    => $types,
    'searchPh' => $view->t('common.admin.search_dict_ph'),
    'addLabel' => $view->t('common.admin.action_add'),
]); ?>

<?php $view->include('dictionary-form', [
    'resource' => 'types',
    'nameOne'  => $view->t('common.admin.type_one'),
]); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
