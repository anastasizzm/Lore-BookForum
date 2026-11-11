<?php $view->extends('main'); ?>
<?php $tr = fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
/*
 * drafts — страница черновиков пользователя (DR-02, DR-03, DR-08).
 * JS: filters.js (фильтры, сортировка, список и «Показать ещё» — режим data-list="drafts").
 * Publish / Delete живут на экране редактирования: карточка ведёт туда кликом.
 */

$view->setBlock('selectedTab', 'drafts');

$drafts = $drafts ?? $items ?? [];
$meta   = $meta ?? [];

if (!is_array($drafts)) $drafts = [];

// Controllers wrap each publication in WithContext; the cards need the item.
$drafts = array_map(
    static fn ($it) => $it instanceof \App\Models\UserContext\WithContext ? $it->item : $it,
    $drafts
);

$page     = max(1, (int) ($meta['page'] ?? 1));
$pageSize = (int) ($meta['pageSize'] ?? 0);
$hasNext  = (bool) ($meta['hasNext'] ?? false);

$coversBase = '/api/files/';
$coversSuffix = '/preview';

$draftDate = static function (object|array $item): string {
    foreach (['updatedAt', 'createdAt'] as $key) {
        $raw = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
        if ($raw instanceof \DateTimeInterface) return $raw->format('d.m.Y');
        if (is_string($raw) && trim($raw) !== '') return trim($raw);
    }
    return '';
};

// The controller knows the list type (meta.type); models in draft lists may be
// PublicationShort, so reflection alone cannot tell an article from a book.
$defaultDraftType = (($meta['type'] ?? 'book') === 'article') ? 'article' : 'book';

$draftType = static function (object|array $item, string $default = 'book'): string {
    $raw = is_array($item) ? ($item['type'] ?? null) : ($item->type ?? null);
    if (is_string($raw)) {
        $raw = strtolower(trim($raw));
        if (in_array($raw, ['book', 'article'], true)) return $raw;
    }
    if (is_object($item)) {
        $class = strtolower((new \ReflectionClass($item))->getShortName());
        if ($class === 'article') return 'article';
        if ($class === 'book') return 'book';
    }
    return $default;
};

$pick = static function (object|array $item, array $keys, string $default = '') use ($coversBase, $coversSuffix): string {
    foreach ($keys as $key) {
        $raw = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
        if ($raw instanceof \Stringable) $raw = (string) $raw;
        if ($raw === null || $raw === '') continue;
        if (is_string($raw)) {
            if ($key === 'icon' || $key === 'iconId') return $coversBase . rawurlencode($raw) . $coversSuffix;
            return $raw;
        }
    }
    return $default;
};

$cards = [];
foreach ($drafts as $one) {
    if (!is_array($one) && !is_object($one)) continue;
    $id = (int) (is_array($one) ? ($one['id'] ?? 0) : ($one->id ?? 0));
    if ($id <= 0) continue;

    $cards[] = [
        'id'    => $id,
        'type'  => $draftType($one, $defaultDraftType),
        'title' => $pick($one, ['title'], ''),
        'cover' => $pick($one, ['cover', 'iconUrl', 'icon', 'iconId']),
        'date'  => $draftDate($one),
    ];
}

$currentLang = $_GET['lang'] ?? null;
if (!is_string($currentLang) || $currentLang === '') {
    $currentLang = method_exists($view, 'locale') ? $view->locale() : null;
}

$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$isArticlesDrafts = str_starts_with($currentPath, '/articles');

$pageLink = static function (int $to) use ($currentPath, $currentLang): string {
    parse_str((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_QUERY), $q);
    if ($to > 1) $q['page'] = $to; else unset($q['page']);
    if ($currentLang !== null && $currentLang !== '') $q['lang'] = $currentLang;
    $qs = http_build_query($q);
    return $currentPath . ($qs !== '' ? '?' . $qs : '');
};

$shown = ($page - 1) * $pageSize + count($cards);

// Есть ли активные фильтры: тогда пустой список — результат фильтра, а не «черновиков нет совсем»
$hasFilters = false;
foreach (['q', 'genre', 'kind', 'ctype'] as $fk) {
    if (isset($_GET[$fk]) && $_GET[$fk] !== '') { $hasFilters = true; break; }
}
$isEmptyAll = empty($cards) && $page <= 1 && !$hasNext && !$hasFilters;

// По умолчанию панель фильтров открыта; закрыта только при ?f=closed (как в library-filters)
$fRaw        = $_GET['f'] ?? null;
$filter_open = !(is_string($fRaw) && strtolower($fRaw) === 'closed');
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
?>

<?php $view->startBlock('title'); ?><?= $tr('common.drafts.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/pages/drafts.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php
// Поиск и кнопка фильтров нужны, только когда есть что фильтровать
$pageActions = '';
if (!$isEmptyAll) {
    ob_start();
    $view->include('input', [
        'type'        => 'search',
        'name'        => 'q',
        'placeholder' => $view->t('common.feed.search'),
        'value'       => $searchQuery,
    ]);
    ?>
    <button type="button"
            class="btn-icon filter-toggle <?= $filter_open ? 'is-active' : '' ?>"
            data-filter-toggle
            aria-label="<?= $tr('common.common.filters') ?>">
      <span>&#9776;</span>
    </button>
    <?php
    $pageActions = ob_get_clean();
}

$view->include('page-header', [
    'title'    => $view->t('common.drafts.title'),
    'subtitle' => $view->t('common.drafts.subtitle'),
    'actions'  => $pageActions,
]);

if (!$isEmptyAll) {
    $view->include('library-filters', ['mode' => 'drafts']);
}
?>

<?php if ($isEmptyAll): ?>

  <section class="books-panel drafts-panel">
    <div class="empty-state drafts-empty">
      <div class="drafts-empty__icon" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"/>
          <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
        </svg>
      </div>
      <p class="empty-state__text drafts-empty__text"><?= $tr('common.drafts.empty') ?></p>
      <p class="empty-state__text drafts-empty__hint"><?= $tr('common.drafts.empty_hint') ?></p>
      <a class="btn btn--primary drafts-empty__cta"
         href="<?= $view->e(($isArticlesDrafts ? '/articles/create' : '/books/create')
             . (($currentLang !== null && $currentLang !== '') ? '?lang=' . urlencode((string) $currentLang) : '')) ?>">
        <?= $tr($isArticlesDrafts ? 'common.drafts.empty_link_article' : 'common.drafts.empty_link') ?>
      </a>
    </div>
  </section>

<?php else: ?>

  <section class="books-panel drafts-panel"
           data-drafts
           data-list="drafts"
           data-list-hydrate
           data-api-books="/api/books"
           data-api-articles="/api/articles"
           data-initial-page="<?= $page ?>"
           data-initial-has-next="<?= $hasNext ? '1' : '0' ?>">

    <header class="books-panel__head">
      <div class="books-panel__title-wrap">
        <div class="books-panel__icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20h9"/>
            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
          </svg>
        </div>
        <div>
          <h2 class="books-panel__title"><?= $tr('common.drafts.panel_title') ?></h2>
          <p class="books-panel__meta">
            <span data-drafts-count><?= $shown ?></span><span data-drafts-plus <?= $hasNext ? '' : 'hidden' ?>>+</span>
            <?= $tr('common.drafts.count_suffix') ?>
          </p>
        </div>
      </div>

      <?php $view->include('list-sort'); ?>
    </header>

    <?php /* Пустое сообщение, сетка и «Показать ещё» рендерятся всегда: filters.js переключает их без перезагрузки */ ?>
    <p class="empty-state__text" data-drafts-empty <?= empty($cards) ? '' : 'hidden' ?>><?= $tr('common.drafts.none_page') ?></p>

    <div class="grid-books drafts-grid" data-drafts-grid <?= empty($cards) ? 'hidden' : '' ?>>
      <?php foreach ($cards as $card): ?>
        <?php $view->include('card-draft', $card); ?>
      <?php endforeach; ?>
    </div>

    <div class="feed-panel__load-more" data-drafts-more <?= $hasNext ? '' : 'hidden' ?>>
      <button type="button" class="btn btn--secondary" data-drafts-load-more>
        <?= $tr('common.common.load_more') ?>
      </button>
    </div>

  </section>

<?php endif; ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/filters.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>