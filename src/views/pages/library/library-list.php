<?php
$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// $items больше не нужен для рендера, но оставим на случай SSR-fallback
$items = $items ?? [];

// Определяем раздел по meta.type
$type       = $meta['type'] ?? null;
$isArticles = $type === 'article';

if ($type === null) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $isArticles  = str_starts_with($currentPath, '/articles');
}

$pageTitle  = $isArticles ? $view->t('common.library.all_articles') : $view->t('common.library.all_books');
// Таб в сайдбаре — «Библиотека» и для книг, и для статей: отдельной
// иконки «Статьи» в sidebar нет, поэтому active-плашка должна гореть
// на обоих разделах.
$tabKey     = 'library';
$emptyText  = $isArticles ? $view->t('common.library.no_articles') : $view->t('common.library.no_books');
$searchHint = $isArticles ? $view->t('common.library.search_articles') : $view->t('common.library.search_books');
$apiBase    = $isArticles ? '/api/articles'  : '/api/books';

$view->setBlock('selectedTab', $tabKey);
$view->startBlock('title'); ?><?= $view->e($pageTitle) ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title');

$view->startBlock('content');

// ---- Поиск + кнопка фильтров в шапке страницы ----
ob_start();
$view->include('input', [
    'type'        => 'search',
    'name'        => 'q',
    'placeholder' => $searchHint,
    'value'       => $searchQuery ?? '',
]);
?>
<button type="button"
        class="btn-icon filter-toggle"
        data-filter-toggle
        aria-label="<?= $tr('common.common.filters') ?>">
  <span>&#9776;</span>
</button>
<?php
$pageActions = ob_get_clean();

$view->include('page-header', [
    'title'   => $view->t('common.library.title'),
    'actions' => $pageActions,
]);

// ---- Панель фильтров ----
$view->include('library-filters', [
    'filterState' => $filterState ?? 'closed',
]);

// ---- Сортировка ----
$sort_options = $sortOptions ?? [
    'newest'     => $view->t('common.sort.newest'),
    'popularity' => $view->t('common.sort.popularity'),
    'alpha'      => $view->t('common.sort.alpha'),
];

$current_sort  = $currentSort ?? 'newest';
if (!isset($sort_options[$current_sort])) $current_sort = 'newest';
$current_label = $sort_options[$current_sort];

// href — только страховка на случай, если JS не загрузился.
// Сохраняем текущие параметры (включая lang), чтобы переход без JS
// не терял язык.
$preservedKeys = ['q', 'genre', 'status', 'isbn', 'doi', 'kind', 'f', 'lang'];
$preserved = array_filter(
    $_GET,
    static fn($k) => in_array($k, $preservedKeys, true),
    ARRAY_FILTER_USE_KEY
);

$dropdownOptions = [];
foreach ($sort_options as $key => $text) {
    $sortHref = '?' . http_build_query(array_merge($preserved, ['sort' => $key]));
    $dropdownOptions[] = [
        'label' => $text,
        'href'  => $sortHref,
        'value' => $key,
    ];
}
?>

<section class="books-panel"
         data-library
         data-api="<?= $view->e($apiBase) ?>">

  <div hidden data-csrf><?= $view->csrfField() ?></div>

  <header class="books-panel__head">
    <div class="books-panel__title-wrap">
      <div class="book-panel-library__icon">
        <?php if ($isArticles): ?>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/>
            <line x1="8" y1="9"  x2="16" y2="9"  stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            <line x1="8" y1="17" x2="13" y2="17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        <?php else: ?>
          <svg width="24" height="24" viewBox="0 0 25 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <g clip-path="url(#book-panel-library__icon)">
              <path d="M0.00146484 21.1979C0.00146484 22.6736 0.701482 23.428 2.17477 23.428H16.3786C17.8438 23.428 18.5438 22.6736 18.5438 21.1979V2.23005C18.5438 0.754403 17.8438 0 16.3786 0H14.2704C12.7971 0 12.0971 0.754403 12.0971 2.23005V6.57408C11.8611 6.49948 11.5925 6.45802 11.2913 6.45802H6.6842C6.37488 6.45802 6.10627 6.49948 5.87022 6.57408V5.05699C5.87022 3.57305 5.1702 2.82695 3.70504 2.82695H2.17477C0.701482 2.82695 0.00146484 3.57305 0.00146484 5.05699V21.1979ZM1.3608 21.0819V5.15647C1.3608 4.53472 1.67011 4.21139 2.31315 4.21139H3.56666C4.20972 4.21139 4.51088 4.53472 4.51088 5.15647V22.0435H2.31315C1.67011 22.0435 1.3608 21.7202 1.3608 21.0819ZM5.87022 22.0435V8.79585C5.87022 8.16579 6.17952 7.84248 6.82256 7.84248H11.1448C11.7959 7.84248 12.0971 8.16579 12.0971 8.79585V22.0435H5.87022ZM13.4565 22.0435V2.33782C13.4565 1.70777 13.7576 1.38446 14.4007 1.38446H16.2321C16.8833 1.38446 17.1845 1.70777 17.1845 2.33782V21.0819C17.1845 21.7202 16.8833 22.0435 16.2321 22.0435H13.4565ZM6.87954 9.88187C6.87954 10.2052 7.12373 10.4622 7.45747 10.4622H10.518C10.8436 10.4622 11.0878 10.2052 11.0878 9.88187C11.0878 9.56684 10.8436 9.31813 10.518 9.31813H7.45747C7.12373 9.31813 6.87954 9.56684 6.87954 9.88187ZM6.87954 19.9958C6.87954 20.3191 7.12373 20.5762 7.45747 20.5762H10.518C10.8436 20.5762 11.0878 20.3191 11.0878 19.9958C11.0878 19.6809 10.8436 19.4321 10.518 19.4321H7.45747C7.12373 19.4321 6.87954 19.6809 6.87954 19.9958ZM19.4961 21.4797C19.6671 22.9389 20.4241 23.6353 21.8892 23.4363L23.0695 23.2953C24.5346 23.0963 25.137 22.3088 24.9742 20.8331L23.2567 4.78342C23.0939 3.31606 22.3206 2.61139 20.8555 2.81865L19.6751 2.95959C18.2019 3.16684 17.5914 3.9544 17.7624 5.42176L19.4961 21.4797ZM20.8229 21.1979L19.1299 5.36373C19.0647 4.73367 19.3334 4.40207 19.9763 4.31916L20.8718 4.21139C21.5148 4.12021 21.8567 4.42695 21.9217 5.04041L23.6149 20.8829C23.6881 21.5212 23.4195 21.8528 22.7765 21.9357L21.8648 22.0435C21.2299 22.1347 20.8962 21.8363 20.8229 21.1979Z" fill="currentColor"/>
            </g>
            <defs>
              <clipPath id="book-panel-library__icon">
                <rect width="25" height="24" fill="white"/>
              </clipPath>
            </defs>
          </svg>
        <?php endif; ?>
      </div>
      <div>
        <h2 class="books-panel__title"><?= $view->e($pageTitle) ?></h2>
        <p class="books-panel__meta">
          <span data-library-count>0</span> <?= $tr('common.common.items') ?>
        </p>
      </div>
    </div>

    <?php
    $view->include('dropdown', [
        'label'   => $view->t('common.sort.label', ['value' => $current_label]),
        'key'     => 'sort',
        'options' => $dropdownOptions,
    ]);
    ?>
  </header>

  <div class="empty-state" data-library-empty hidden>
    <p class="empty-state__text"><?= $view->e($emptyText) ?></p>
  </div>

  <?php /* Пустой контейнер — карточки отрисует JS из ответа API */ ?>
  <div class="grid-books" data-library-grid></div>

  <div class="feed-panel__load-more" data-library-more hidden>
    <button type="button" class="btn btn--secondary" data-load-more><?= $tr('common.common.load_more') ?></button>
  </div>

</section>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/library-filters.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>