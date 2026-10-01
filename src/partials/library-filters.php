<?php
/**
 * library-filters - filter panel for library page.
 */

$filterState = $filterState ?? 'closed';
$filter_open = $filterState === 'open';
$fQs = $filter_open ? '?f=open' : '';

$bookIcon = '<svg viewBox="0 0 24 24"><path d="M3 5a2 2 0 0 1 2-2h5v16H5a2 2 0 0 0-2 2V5z"/><path d="M21 5a2 2 0 0 0-2-2h-5v16h5a2 2 0 0 1 2 2V5z"/></svg>';
$postIcon = '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isArticles  = str_starts_with($currentPath, '/articles');

$switcher = [
    'type'    => 'tabs',
    'variant' => 'segmented',
    'items' => [
        [
            'label'  => 'Books',
            'href'   => '/books' . $fQs,
            'icon'   => $bookIcon,
            'active' => !$isArticles,
        ],
        [
            'label'  => 'Articles',
            'href'   => '/articles' . $fQs,
            'icon'   => $postIcon,
            'active' => $isArticles,
        ],
    ],
];

$filter_rows = [
    [
        'id'     => 'books',
        'hidden' => $isArticles,
        'controls' => [
            $switcher,
            [
                'type'    => 'dropdown',
                'label'   => 'Genre',
                'dynamic' => 'genres',
                'options' => [],
            ],
            [
                'type'    => 'tabs',
                'variant' => 'segmented',
                'items' => [
                    ['label' => 'All',      'href' => '#all',     'active' => true],
                    ['label' => 'Reading',  'href' => '#reading'],
                    ['label' => 'Finished', 'href' => '#finished'],
                ],
            ],
            ['type' => 'input', 'name' => 'series', 'placeholder' => 'Series number'],
            ['type' => 'reset'],
        ],
    ],
    [
        'id'     => 'articles',
        'hidden' => !$isArticles,
        'controls' => [
            $switcher,
            [
                'type'    => 'dropdown',
                'label'   => 'Genre',
                'dynamic' => 'genres',
                'options' => [],
            ],
            [
                'type'    => 'dropdown',
                'label'   => 'Type',
                'options' => [
                    ['label' => 'All',      'href' => '?kind=all'],
                    ['label' => 'Review',   'href' => '?kind=review'],
                    ['label' => 'Critique', 'href' => '?kind=critique'],
                    ['label' => 'Essay',    'href' => '?kind=essay'],
                    ['label' => 'Note',     'href' => '?kind=note'],
                ],
            ],
            ['type' => 'reset'],
        ],
    ],
];

$view->include('filter-panel', [
    'filter_rows' => $filter_rows,
    'filter_open' => $filter_open,
]);