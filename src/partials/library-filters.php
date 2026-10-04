<?php
/**
 * library-filters - filter panel (library + profile publications).
 * Optional vars: $isArticles, $basePath, $bookHref, $articleHref, $filterState
 */
$filterState = $filterState ?? 'closed';
$filter_open = $filterState === 'open';

$bookIcon = '<svg viewBox="0 0 24 24"><path d="M3 5a2 2 0 0 1 2-2h5v16H5a2 2 0 0 0-2 2V5z"/><path d="M21 5a2 2 0 0 0-2-2h-5v16h5a2 2 0 0 1 2 2V5z"/></svg>';
$postIcon = '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isArticles  = $isArticles  ?? str_contains($currentPath, '/articles');
$basePath    = $basePath    ?? $currentPath;
$bookHref    = $bookHref    ?? '/books';
$articleHref = $articleHref ?? '/articles';

// Current URL params
parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
unset($params['page']);
if ($filter_open) {
    $params['f'] = 'open';
} else {
    unset($params['f']);
}

// Link to the current page with changed params
$link = static function (array $set = [], array $drop = []) use ($params, $basePath): string {
    $p = array_diff_key(array_merge($params, $set), array_flip($drop));
    return $basePath . ($p ? '?' . http_build_query($p) : '');
};

// When switching Books/Articles keep only shared params
$keep = array_intersect_key($params, array_flip(['f', 'q', 'genre']));
$tabHref = static function (string $href) use ($keep): string {
    if (!$keep) return $href;
    return $href . (str_contains($href, '?') ? '&' : '?') . http_build_query($keep);
};

$str = static fn(string $key): string => is_string($params[$key] ?? null) ? $params[$key] : '';

$status = strtolower($str('status'));
$genre  = $str('genre');
$kind   = strtolower($str('kind'));
$isbn   = $str('isbn');
$doi    = $str('doi');

$switcher = [
    'type'    => 'tabs',
    'variant' => 'segmented',
    'items' => [
        ['label' => 'Books',    'href' => $tabHref($bookHref),    'icon' => $bookIcon, 'active' => !$isArticles],
        ['label' => 'Articles', 'href' => $tabHref($articleHref), 'icon' => $postIcon, 'active' => $isArticles],
    ],
];

// Genre dropdown — динамический, из /api/additional/genres
$genreDropdown = [
    'type'    => 'dropdown',
    'label'   => 'Genre',
    'key'     => 'genre',
    'dynamic' => 'genres',
    'options' => [],
];

// Type dropdown — статический, значения совпадают с enum ArticleType
$typeDropdown = [
    'type'    => 'dropdown',
    'label'   => 'Type',
    'key'     => 'kind',
    'dynamic' => 'types',       // ← теперь грузим через fetch
    'options' => [],
];

// Reset clears filters but keeps search (q) and panel state (f)
$resetHref = $link([], ['genre', 'status', 'isbn', 'doi', 'series', 'kind', 'sort']);

$filter_rows = [
    [
        'id'     => 'books',
        'hidden' => $isArticles,
        'controls' => [
            $switcher,
            $genreDropdown,
            [
                'type'    => 'tabs',
                'variant' => 'segmented',
                'items' => [
                    // 'value' => 'finished' (UI), JS маппит в 'ended' (API) через STATUS_VALUES
                    ['label' => 'All',      'href' => $link([], ['status']),           'key' => 'status', 'value' => 'all',      'active' => $status === ''],
                    ['label' => 'Reading',  'href' => $link(['status' => 'reading']),  'key' => 'status', 'value' => 'reading',  'active' => $status === 'reading'],
                    ['label' => 'Finished', 'href' => $link(['status' => 'finished']), 'key' => 'status', 'value' => 'finished', 'active' => $status === 'finished'],
                ],
            ],
            [
                'type'        => 'input',
                'name'        => 'isbn',
                'placeholder' => 'ISBN',
                'value'       => $isbn,
                'format'      => 'isbn',
                'maxlength'   => 17,
                'inputmode'   => 'text',
            ],
            ['type' => 'reset', 'href' => $resetHref],
        ],
    ],
    [
        'id'     => 'articles',
        'hidden' => !$isArticles,
        'controls' => [
            $switcher,
            $genreDropdown,
            $typeDropdown,
            [
                'type'        => 'input',
                'name'        => 'doi',
                'placeholder' => 'DOI',
                'value'       => $doi,
                'format'      => 'doi',
                'maxlength'   => 200,
                'inputmode'   => 'text',
            ],
            ['type' => 'reset', 'href' => $resetHref],
        ],
    ],
];

$view->include('filter-panel', [
    'filter_rows' => $filter_rows,
    'filter_open' => $filter_open,
]);