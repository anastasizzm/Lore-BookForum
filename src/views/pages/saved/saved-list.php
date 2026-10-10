<?php $view->extends('main'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php $view->setBlock('selectedTab', 'saved'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.saved.title') ?> — <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('content'); ?>

<?php
/**
 * Данные от BooksController::savedList / ArticlesController::savedList:
 *   $items — массив Publication
 *   $meta  — ['page', 'pageSize', 'hasNext', 'type']
 */
$items    = $items ?? [];
$meta     = $meta  ?? [];

$items = array_map(
    static fn ($it) => $it instanceof \App\Models\UserContext\WithContext ? $it->item : $it,
    $items
);
$page     = max(1, (int) ($meta['page'] ?? 1));
$pageSize = (int) ($meta['pageSize'] ?? 0);
$hasNext  = (bool) ($meta['hasNext'] ?? false);

$isArticles = ($meta['type'] ?? 'book') === 'article';

$q = $searchQuery ?? ($_GET['q'] ?? '');
$q = is_string($q) ? trim($q) : '';

$statusParam = strtolower((string) ($_GET['status'] ?? ''));
if (!in_array($statusParam, ['reading', 'ended'], true)) $statusParam = null;

$f = match ($statusParam) {
    'reading' => 'to-read',
    'ended'   => 'finished',
    default   => 'all',
};

$sortOptions = $sortOptions ?? [
    'newest'     => $view->t('common.sort.newest'),
    'popularity' => $view->t('common.sort.popularity'),
    'alpha'      => $view->t('common.sort.alpha'),
];
$sort = $currentSort ?? ($_GET['sort'] ?? '');
if (!is_string($sort) || !isset($sortOptions[$sort])) $sort = 'newest';
$sortLabel = $sortOptions[$sort];

$coversBase = '/uploads/covers/';

$cards = [];
foreach ($items as $p) {
    $creator = $p->creator ?? null;

    $author = $creator ? trim(($creator->name ?? '') . ' ' . ($creator->surname ?? '')) : '';
    if ($author === '' && $creator) $author = (string) ($creator->username ?? '');

    $icon = $p->iconId ?? null;

    $cards[] = [
        'id'       => (int) $p->id,
        'cover'    => $icon instanceof Stringable ? $coversBase . $icon : '',
        'title'    => (string) $p->title,
        'authorId' => (int) ($creator->id ?? $p->getCreatorId()),
        'author'   => $author,
    ];
}

// Локаль для всех ссылок страницы: если её не сохранять, клик по сортировке
// или пагинации уводит на URL без ?lang=, и бэк переключается на fallback.
$currentLang = $_GET['lang'] ?? null;
if (!is_string($currentLang) || $currentLang === '') {
    $currentLang = method_exists($view, 'locale') ? $view->locale() : null;
}

$baseUrl = $view->url('books.saved');
$link = static function (array $params) use ($baseUrl, $sort, $statusParam, $currentLang): string {
    $params += [
        'sort'   => $sort === 'newest' ? null : $sort,
        'status' => $statusParam,
        'lang'   => $currentLang,
    ];
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    $qs = http_build_query($params);
    return $baseUrl . ($qs !== '' ? '?' . $qs : '');
};

$shown = ($page - 1) * $pageSize + count($cards);
?>

<?php
ob_start();
$view->include('input', [
    'type'        => 'search',
    'name'        => 'q',
    'placeholder' => $view->t('common.saved.search'),
    'value'       => $q,
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
    'title'    => $view->t('common.saved.title'),
    'subtitle' => $view->t('common.saved.subtitle'),
    'actions'  => $pageActions,
]);

$view->include('library-filters', [
    'filterState' => $filterState ?? 'closed',
    'bookHref'    => '/books/saved',
    'articleHref' => '/articles/saved',
]);
?>

<?php if (empty($cards) && $q === '' && $f === 'all' && $page === 1): ?>

  <div class="empty-state">
    <p class="empty-state__text">
      <?= $tr('common.saved.empty_before') ?>
      <a href="<?= $view->e($view->url('books')) ?>" class="link"><?= $tr('common.saved.empty_link') ?></a>
      <?= $tr('common.saved.empty_after') ?>
    </p>
  </div>

<?php else: ?>

  <section class="books-panel" data-saved-page>

    <div hidden data-csrf><?= $view->csrfField() ?></div>

    <header class="books-panel__head">
      <div class="books-panel__title-wrap">
        <div class="books-panel__icon">
          <svg width="20" height="20" viewBox="0 0 17 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 2.5C2 1.83696 2.21401 1.20107 2.59494 0.732233C2.97587 0.263392 3.49253 0 4.03125 0L12.1562 0C12.695 0 13.2116 0.263392 13.5926 0.732233C13.9735 1.20107 14.1875 1.83696 14.1875 2.5V19.375C14.1875 19.4881 14.1625 19.599 14.1153 19.6959C14.0681 19.7929 14.0004 19.8723 13.9194 19.9257C13.8384 19.979 13.7472 20.0044 13.6554 19.999C13.5637 19.9936 13.4748 19.9576 13.3984 19.895L8.09375 16.3762L2.78914 19.895C2.71267 19.9576 2.62383 19.9936 2.53208 19.999C2.44033 20.0044 2.3491 19.979 2.26812 19.9257C2.18714 19.8723 2.11944 19.7929 2.07223 19.6959C2.02501 19.599 2.00005 19.4881 2 19.375V2.5ZM4.03125 1.25C3.76189 1.25 3.50356 1.3817 3.31309 1.61612C3.12263 1.85054 3.01562 2.16848 3.01562 2.5V18.2075L7.81242 15.105C7.89576 15.0367 7.99364 15.0003 8.09375 15.0003C8.19386 15.0003 8.29174 15.0367 8.37508 15.105L13.1719 18.2075V2.5C13.1719 2.16848 13.0649 1.85054 12.8744 1.61612C12.6839 1.3817 12.4256 1.25 12.1562 1.25H4.03125Z" fill="currentColor"/>
          </svg>
        </div>
        <div>
          <h2 class="books-panel__title"><?= $tr('common.saved.panel_title') ?></h2>
          <p class="books-panel__meta">
            <span data-saved-count><?= $shown ?></span><?= $hasNext ? '+' : '' ?> <?= $tr('common.saved.meta_suffix') ?>
          </p>
        </div>
      </div>

      <?php
      $sortDropdown = [];
      foreach ($sortOptions as $key => $text) {
          $sortDropdown[] = [
              'label' => $text,
              'href'  => $link(['q' => $q, 'sort' => $key]),
              'value' => $key,
          ];
      }
      $view->include('dropdown', [
          'label'   => $view->t('common.sort.label', ['value' => $sortLabel]),
          'key'     => 'sort',
          'options' => $sortDropdown,
      ]);
      ?>
    </header>

    <?php if (empty($cards)): ?>
      <p class="empty-state__text">
        <?= $q !== '' ? $tr('common.saved.not_found') : $tr('common.saved.none_here') ?>
        <?php if ($page > 1): ?>
          <a href="<?= $view->e($link(['q' => $q])) ?>" class="link"><?= $tr('common.saved.first_page') ?></a>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <div class="grid-books" data-saved-grid>
        <?php foreach ($cards as $card): ?>
          <?php $view->include('card-book', $card + [
              'saved' => true,
              'saveType' => $isArticles ? 'article' : 'book',
          ]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="empty-state__text" data-saved-empty hidden>
      <?= $tr('common.saved.none_left') ?>
      <a href="<?= $view->e($link(['q' => $q])) ?>" class="link"><?= $tr('common.saved.reload') ?></a>
    </p>

    <?php if ($page > 1 || $hasNext): ?>
      <nav class="books-panel__pager" aria-label="<?= $tr('common.saved.pagination') ?>">
        <?php if ($page > 1): ?>
          <a class="btn btn--secondary"
             href="<?= $view->e($link(['q' => $q, 'page' => $page > 2 ? $page - 1 : null])) ?>"><?= $tr('common.saved.prev') ?></a>
        <?php endif; ?>
        <?php if ($hasNext): ?>
          <a class="btn btn--secondary"
             href="<?= $view->e($link(['q' => $q, 'page' => $page + 1])) ?>"><?= $tr('common.saved.next') ?></a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  </section>

<?php endif; ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="<?= $view->asset('js/saved.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>