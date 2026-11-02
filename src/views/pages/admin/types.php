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

// Данные контроллера (если он их передал) — до того, как переменную перезапишет мок ниже
$serverItems = $types ?? $items ?? null;

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
