<?php
/**
 * list-sort — выпадающий список сортировки (новизна / популярность / алфавит).
 * Общий для profile-publications.php и drafts.php. Размещается в <header class="books-panel__head">.
 * Ссылки сохраняют текущие фильтры; filters.js перехватывает клик и обновляет список запросом в API.
 * Optional vars: $sortOptions, $currentSort
 */
$sort_options = $sortOptions ?? [
    'newest'     => $view->t('common.sort.newest'),
    'popularity' => $view->t('common.sort.popularity'),
    'alpha'      => $view->t('common.sort.alpha'),
];

$current_sort = $currentSort ?? ($_GET['sort'] ?? 'newest');
if (!is_string($current_sort) || !isset($sort_options[$current_sort])) $current_sort = 'newest';
$current_label = $sort_options[$current_sort];

$preservedKeys = ['q', 'genre', 'status', 'p_status', 'isbn', 'doi', 'kind', 'ctype', 'f', 'lang'];
$preserved = array_filter(
    $_GET,
    static fn($k) => in_array($k, $preservedKeys, true),
    ARRAY_FILTER_USE_KEY
);

$dropdownOptions = [];
foreach ($sort_options as $key => $text) {
    $dropdownOptions[] = [
        'label' => $text,
        'href'  => '?' . http_build_query(array_merge($preserved, ['sort' => $key])),
        'value' => $key,
    ];
}

$view->include('dropdown', [
    'label'   => $view->t('common.sort.label', ['value' => $current_label]),
    'key'     => 'sort',
    'options' => $dropdownOptions,
]);