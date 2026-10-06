<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'saved'); ?>

<?php $view->startBlock('title'); ?>Saved — Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('content'); ?>

<?php
/**
 * Данные от BooksController::savedList / ArticlesController::savedList:
 *   $items — массив Publication (id, title, iconId, creator, getCreatorId()...)
 *   $meta  — ['page', 'pageSize', 'hasNext', 'type']
 * Необязательно: $searchQuery, $currentFilter (иначе берутся из ?q= и ?rf=)
 */
$items    = $items ?? [];
$meta     = $meta  ?? [];

/**
 * Бэкенд отдаёт элементы обёрнутыми в WithContext<TItem, TContext> (item + контекст
 * юзера: isSaved / isEditor / readingStatus). Вьюхе нужен сам объект публикации —
 * разворачиваем, чтобы не ловить "undefined property" и "getCreatorId() on WithContext".
 */
$items = array_map(
    static fn ($it) => $it instanceof \App\Models\UserContext\WithContext ? $it->item : $it,
    $items
);
$page     = max(1, (int) ($meta['page'] ?? 1));
$pageSize = (int) ($meta['pageSize'] ?? 0);
$hasNext  = (bool) ($meta['hasNext'] ?? false);

$q = $searchQuery ?? ($_GET['q'] ?? '');
$q = is_string($q) ? trim($q) : '';

// ВАЖНО: 'f' занят под состояние панели фильтров (open/closed) в app.js.
// Для табов Saved используем отдельный параметр 'rf'.
// all -> все сохранённые, to-read -> в процессе чтения, finished -> дочитанные
$f = $currentFilter ?? ($_GET['rf'] ?? 'all');
if (!in_array($f, ['all', 'to-read', 'finished'], true)) $f = 'all';

// TODO: уточнить у бэка, как отдаются обложки по icon_id (files.id)
$coversBase = '/uploads/covers/';

// Publication -> параметры для card-book
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

// Ссылки сохраняют поиск и фильтр
$baseUrl = $view->url('books.saved');
$link = static function (array $params) use ($baseUrl): string {
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    $qs = http_build_query($params);
    return $baseUrl . ($qs !== '' ? '?' . $qs : '');
};
$filterParam = $f === 'all' ? null : $f;

// Сколько книг показано (нижняя граница, если есть следующая страница)
$shown = ($page - 1) * $pageSize + count($cards);
?>

<?php
// В page-header кладём поиск + кнопку фильтров.
// Enter в поле поиска ловит filters.js::setupSearch и сохраняет остальные параметры.
ob_start();
$view->include('input', [
    'type'        => 'search',
    'name'        => 'q',
    'placeholder' => 'Search saved books',
    'value'       => $q,
]);
?>
<button type="button"
        class="btn-icon filter-toggle"
        data-filter-toggle
        aria-label="Filters">
  <span>&#9776;</span>
</button>
<?php
$pageActions = ob_get_clean();

$view->include('page-header', [
    'title'    => 'Saved',
    'subtitle' => 'Your bookmarked books, discussions, and reading lists.',
    'actions'  => $pageActions,
]);

// Панель фильтров: Books ↔ Articles + Genre / Status / Type
// bookHref/articleHref ведут на saved-варианты, чтобы табы не уводили в общую библиотеку
$view->include('library-filters', [
    'filterState' => $filterState ?? 'closed',
    'bookHref'    => '/books/saved',
    'articleHref' => '/articles/saved',
]);
?>

<?php if (empty($cards) && $q === '' && $f === 'all' && $page === 1): ?>

  <div class="empty-state">
    <p class="empty-state__text">
      You have no saved books yet.
      <a href="<?= $view->e($view->url('books')) ?>" class="link">Browse the library</a>
      and save what you like.
    </p>
  </div>

<?php else: ?>

  <section class="books-panel">

    <!-- CSRF-токен для fetch-запросов (save) -->
    <div hidden data-csrf><?= $view->csrfField() ?></div>

    <header class="books-panel__head">
      <div class="books-panel__title-wrap">
        <div class="books-panel__icon">
          <svg width="20" height="20" viewBox="0 0 17 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 2.5C2 1.83696 2.21401 1.20107 2.59494 0.732233C2.97587 0.263392 3.49253 0 4.03125 0L12.1562 0C12.695 0 13.2116 0.263392 13.5926 0.732233C13.9735 1.20107 14.1875 1.83696 14.1875 2.5V19.375C14.1875 19.4881 14.1625 19.599 14.1153 19.6959C14.0681 19.7929 14.0004 19.8723 13.9194 19.9257C13.8384 19.979 13.7472 20.0044 13.6554 19.999C13.5637 19.9936 13.4748 19.9576 13.3984 19.895L8.09375 16.3762L2.78914 19.895C2.71267 19.9576 2.62383 19.9936 2.53208 19.999C2.44033 20.0044 2.3491 19.979 2.26812 19.9257C2.18714 19.8723 2.11944 19.7929 2.07223 19.6959C2.02501 19.599 2.00005 19.4881 2 19.375V2.5ZM4.03125 1.25C3.76189 1.25 3.50356 1.3817 3.31309 1.61612C3.12263 1.85054 3.01562 2.16848 3.01562 2.5V18.2075L7.81242 15.105C7.89576 15.0367 7.99364 15.0003 8.09375 15.0003C8.19386 15.0003 8.29174 15.0367 8.37508 15.105L13.1719 18.2075V2.5C13.1719 2.16848 13.0649 1.85054 12.8744 1.61612C12.6839 1.3817 12.4256 1.25 12.1562 1.25H4.03125Z" fill="currentColor"/>
          </svg>
        </div>
        <div>
          <h2 class="books-panel__title">Saved books</h2>
          <p class="books-panel__meta">
            <span data-saved-count><?= $shown ?></span><?= $hasNext ? '+' : '' ?> items · Updated today
          </p>
        </div>
      </div>

      <?php
      // Табы фильтра Saved: 'rf' — отдельный параметр, не конфликтует с f=open/closed
      $view->include('tabs', [
          'variant' => 'filled',
          'items'   => [
              ['label' => 'All',      'href' => $link(['q' => $q]),                      'active' => $f === 'all'],
              ['label' => 'To read',  'href' => $link(['q' => $q, 'rf' => 'to-read']),   'active' => $f === 'to-read'],
              ['label' => 'Finished', 'href' => $link(['q' => $q, 'rf' => 'finished']),  'active' => $f === 'finished'],
          ],
      ]);
      ?>
    </header>

    <?php if (empty($cards)): ?>
      <p class="empty-state__text">
        <?= $q !== '' ? 'Nothing found for your search.' : 'No books here yet.' ?>
        <?php if ($page > 1): ?>
          <a href="<?= $view->e($link(['q' => $q, 'rf' => $filterParam])) ?>" class="link">Back to the first page</a>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <div class="grid-books" data-saved-grid>
        <?php foreach ($cards as $card): ?>
          <?php $view->include('card-book', $card + [
              'saved' => true, // на странице Saved все книги сохранены
              // кнопка Save: /api/articles/{id}/save для статей, /api/books/{id}/save для книг
              'saveType' => ($meta['type'] ?? 'book') === 'article' ? 'article' : 'book',
          ]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Показывается из saved.js, когда на странице сняли закладки со всех книг -->
    <p class="empty-state__text" data-saved-empty hidden>
      No saved books left on this page.
      <a href="<?= $view->e($link(['q' => $q, 'rf' => $filterParam])) ?>" class="link">Reload</a>
    </p>

    <?php if ($page > 1 || $hasNext): ?>
      <nav class="books-panel__pager" aria-label="Pagination">
        <?php if ($page > 1): ?>
          <a class="btn btn--secondary"
             href="<?= $view->e($link(['q' => $q, 'rf' => $filterParam, 'page' => $page > 2 ? $page - 1 : null])) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($hasNext): ?>
          <a class="btn btn--secondary"
             href="<?= $view->e($link(['q' => $q, 'rf' => $filterParam, 'page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  </section>

<?php endif; ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="<?= $view->asset('js/saved.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>