<?php
/**
 * library-filters — панель фильтров для библиотеки.
 */

$bookIcon = '<svg viewBox="0 0 24 24"><path d="M3 5a2 2 0 0 1 2-2h5v16H5a2 2 0 0 0-2 2V5z"/><path d="M21 5a2 2 0 0 0-2-2h-5v16h5a2 2 0 0 1 2 2V5z"/></svg>';
$postIcon = '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>';

// Определяем текущий раздел по URL
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isArticles  = str_starts_with($currentPath, '/articles');

// Переключатель разделов — общий для обоих рядов
$switcher = [
    'type'    => 'tabs',
    'variant' => 'segmented',
    'items' => [
        [
            'label'  => 'Книги',
            'href'   => '/books',
            'icon'   => $bookIcon,
            'active' => !$isArticles,
        ],
        [
            'label'  => 'Статьи',
            'href'   => '/articles',
            'icon'   => $postIcon,
            'active' => $isArticles,
        ],
    ],
];

$filter_rows = [
    [
        'id'     => 'books',
        'hidden' => $isArticles,     // скрыт, если мы на /articles
        'controls' => [
            $switcher,
            [
                'type'    => 'dropdown',
                'label'   => 'Жанр',
                'options' => [
                    ['label' => 'Все жанры',  'href' => '?genre=all'],
                    ['label' => 'Фантастика', 'href' => '?genre=scifi'],
                    ['label' => 'Роман',      'href' => '?genre=novel'],
                ],
            ],
            [
                'type'    => 'tabs',
                'variant' => 'segmented',
                'items' => [
                    ['label' => 'Все',       'href' => '#all',     'active' => true],
                    ['label' => 'Читаю',     'href' => '#reading'],
                    ['label' => 'Прочитано', 'href' => '#read'],
                ],
            ],
            ['type' => 'input', 'name' => 'series', 'placeholder' => 'Номер серии'],
            ['type' => 'reset'],
        ],
    ],
    [
        'id'     => 'articles',
        'hidden' => !$isArticles,    // показан, если мы на /articles
        'controls' => [
            $switcher,
            [
                'type'    => 'dropdown',
                'label'   => 'Жанр',
                'options' => [['label' => 'Все жанры', 'href' => '?genre=all']],
            ],
            [
                'type'    => 'dropdown',
                'label'   => 'Тип статьи',
                'options' => [
                    ['label' => 'Все',      'href' => '?kind=all'],
                    ['label' => 'Обзор',    'href' => '?kind=review'],
                    ['label' => 'Рецензия', 'href' => '?kind=critique'],
                ],
            ],
            ['type' => 'reset'],
        ],
    ],
];

$view->include('filter-panel', ['filter_rows' => $filter_rows]);