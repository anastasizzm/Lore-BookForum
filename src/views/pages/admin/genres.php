<?php
/**
 * admin/genres — таблица справочника «Жанры» (каркас, мок-данные).
 *
 * Статус: каркас + fetch-слой. Строки пока мок-заглушки (список ниже),
 * add/edit/delete идут через dictApi в admin.js: роут
 * GET/POST/PATCH/DELETE /api/admin/genres ещё нет, поэтому слой сам
 * падает в режим mock и правит локальный массив; как только бэк отдаст
 * роут, тот же код уйдёт в сеть без правок этой страницы.
 * Проверок прав здесь нет — их даст бэк (403) вместе с роутом.
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

// Данные контроллера (если он их передал) — до того, как переменную перезапишет мок ниже
$serverItems = $genres ?? $items ?? null;

// ---- МОК-строки: сюда придёт GET /api/admin/genres (dictApi в admin.js) ----
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

<!-- Прячется admin.js, когда GET /api/admin/genres реально отвечает -->
<p class="admin-note" data-dict-stub><?= $tr('common.admin.stub_note') ?></p>

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
