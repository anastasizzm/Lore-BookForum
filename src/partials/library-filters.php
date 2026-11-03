<?php
/**
 * library-filters - filter panel (library + profile publications).
 * Optional vars: $isArticles, $basePath, $bookHref, $articleHref,
 *   $mode ('library' | 'drafts'; по умолчанию library).
 * В режиме drafts: одна строка без табов статуса чтения, переключатель Все/Книги/Статьи (?ctype=).
 * Открыта ли панель - определяется по ?f= (по умолчанию открыта).
 */
// Состояние панели задаёт только ?f=: f=closed - закрыта, всё остальное
// (параметра нет, f=open) - открыта. $filterState из контроллера не используется.
$fRaw        = $_GET['f'] ?? null;
$filter_open = !(is_string($fRaw) && strtolower($fRaw) === 'closed');
$filterState = $filter_open ? 'open' : 'closed';

$bookIcon = '<svg viewBox="0 0 24 24"><path d="M3 5a2 2 0 0 1 2-2h5v16H5a2 2 0 0 0-2 2V5z"/><path d="M21 5a2 2 0 0 0-2-2h-5v16h5a2 2 0 0 1 2 2V5z"/></svg>';
$postIcon = '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$mode        = $mode ?? 'library';
$isDrafts    = $mode === 'drafts';
$isArticles  = $isDrafts ? false : ($isArticles ?? str_contains($currentPath, '/articles'));
$basePath    = $basePath    ?? $currentPath;
$bookHref    = $bookHref    ?? '/books';
$articleHref = $articleHref ?? '/articles';

// Текущая локаль — чтобы не потерять ?lang= при переходах (Books ↔ Articles,
// Reset, табы статуса). Бэк определяет язык именно по этому параметру.
$lang = isset($_GET['lang']) && is_string($_GET['lang'])
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : null);

// Current URL params
parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
unset($params['page']);
$params['f'] = $filter_open ? 'open' : 'closed';
// Локаль всегда в params — дальше все ссылки её унаследуют.
if ($lang) $params['lang'] = $lang;

// Link to the current page with changed params
$link = static function (array $set = [], array $drop = []) use ($params, $basePath): string {
    $p = array_diff_key(array_merge($params, $set), array_flip($drop));
    return $basePath . ($p ? '?' . http_build_query($p) : '');
};

// When switching Books/Articles keep only shared params — ВАЖНО: lang тоже сохраняем,
// иначе при переходе на /articles бэк сбросит локаль на фолбэк.
$keep = array_intersect_key($params, array_flip(['f', 'q', 'genre', 'p_status', 'lang']));
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
        ['label' => $view->t('common.filter.books'),    'href' => $tabHref($bookHref),    'icon' => $bookIcon, 'active' => !$isArticles],
        ['label' => $view->t('common.filter.articles'), 'href' => $tabHref($articleHref), 'icon' => $postIcon, 'active' => $isArticles],
    ],
];

// Genre dropdown — динамический, из /api/additional/genres
$genreDropdown = [
    'type'    => 'dropdown',
    'label'   => $view->t('common.filter.genre'),
    'key'     => 'genre',
    'dynamic' => 'genres',
    'options' => [],
];

// Type dropdown — статический, значения совпадают с enum ArticleType
$typeDropdown = [
    'type'    => 'dropdown',
    'label'   => $view->t('common.filter.type'),
    'key'     => 'kind',
    'dynamic' => 'types',
    'options' => [],
];

// Reset clears filters but keeps search (q), panel state (f) and locale (lang)
$resetHref = $link([], ['genre', 'status', 'p_status', 'isbn', 'doi', 'series', 'kind', 'sort', 'ctype']);

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
                    ['label' => $view->t('common.filter.all'),      'href' => $link([], ['status']),          'key' => 'status', 'value' => 'all',     'active' => $status === ''],
                    ['label' => $view->t('common.filter.reading'),  'href' => $link(['status' => 'reading']), 'key' => 'status', 'value' => 'reading', 'active' => $status === 'reading'],
                    ['label' => $view->t('common.filter.finished'), 'href' => $link(['status' => 'ended']),   'key' => 'status', 'value' => 'ended',   'active' => $status === 'ended'],
                ],
            ],
            [
                'type'        => 'input',
                'name'        => 'isbn',
                'placeholder' => $view->t('common.filter.isbn'),
                'value'       => $isbn,
                'format'      => 'isbn',
                'maxlength'   => 17,
                'inputmode'   => 'text',
            ],
            ['type' => 'reset', 'href' => $resetHref, 'label' => $view->t('common.filter.reset')],
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
                'placeholder' => $view->t('common.filter.doi'),
                'value'       => $doi,
                'format'      => 'doi',
                'maxlength'   => 200,
                'inputmode'   => 'text',
            ],
            ['type' => 'reset', 'href' => $resetHref, 'label' => $view->t('common.filter.reset')],
        ],
    ],
];

// Черновики: книги и статьи вместе. Фильтра по статусу чтения здесь нет —
// у черновиков его не бывает. Переключатель Все / Книги / Статьи — параметр ?ctype=.
if ($isDrafts) {
    $ctype = strtolower($str('ctype'));
    if (!in_array($ctype, ['book', 'article'], true)) $ctype = '';

    $draftsSwitcher = [
        'type'    => 'tabs',
        'variant' => 'segmented',
        'items'   => [
            ['label' => $view->t('common.filter.all'),      'href' => $link([], ['ctype']),                 'icon' => '',        'key' => 'ctype', 'value' => 'all',     'active' => $ctype === ''],
            ['label' => $view->t('common.filter.books'),    'href' => $link(['ctype' => 'book']),           'icon' => $bookIcon, 'key' => 'ctype', 'value' => 'book',    'active' => $ctype === 'book'],
            ['label' => $view->t('common.filter.articles'), 'href' => $link(['ctype' => 'article']),        'icon' => $postIcon, 'key' => 'ctype', 'value' => 'article', 'active' => $ctype === 'article'],
        ],
    ];

    $filter_rows = [
        [
            'id'       => 'drafts',
            'hidden'   => false,
            'controls' => [
                $draftsSwitcher,
                $genreDropdown,
                $typeDropdown,
                ['type' => 'reset', 'href' => $resetHref, 'label' => $view->t('common.filter.reset')],
            ],
        ],
    ];
}

$view->include('filter-panel', [
    'filter_rows' => $filter_rows,
    'filter_open' => $filter_open,
]);