<?php
/**
 * library-filters — панель фильтров для библиотеки.
 * Передаёт данные в filter-panel.
 */

$bookIcon = '<svg viewBox="0 0 24 24"><path d="M3 5a2 2 0 0 1 2-2h5v16H5a2 2 0 0 0-2 2V5z"/><path d="M21 5a2 2 0 0 0-2-2h-5v16h5a2 2 0 0 1 2 2V5z"/></svg>';
$postIcon = '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>';

$filter_rows = [
    [
        'id'     => 'books',
        'hidden' => false,
        'controls' => [
            [
                'type'    => 'tabs',
                'variant' => 'segmented',
                'items' => [
                    ['label' => 'Книги',  'href' => '#books',    'row' => 'books',    'icon' => $bookIcon, 'active' => true],
                    ['label' => 'Статьи', 'href' => '#articles', 'row' => 'articles', 'icon' => $postIcon],
                ],
            ],
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
                'type'    => 'dropdown',
                'label'   => 'Автор',
                'options' => [['label' => 'Все авторы', 'href' => '?author=all']],
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
        'hidden' => true,
        'controls' => [
            [
                'type'    => 'tabs',
                'variant' => 'segmented',
                'items' => [
                    ['label' => 'Книги',  'href' => '#books',    'row' => 'books',    'icon' => $bookIcon],
                    ['label' => 'Статьи', 'href' => '#articles', 'row' => 'articles', 'icon' => $postIcon, 'active' => true],
                ],
            ],
            [
                'type'    => 'dropdown',
                'label'   => 'Жанр',
                'options' => [['label' => 'Все жанры', 'href' => '?genre=all']],
            ],
            [
                'type'    => 'dropdown',
                'label'   => 'Автор',
                'options' => [['label' => 'Все авторы', 'href' => '?author=all']],
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