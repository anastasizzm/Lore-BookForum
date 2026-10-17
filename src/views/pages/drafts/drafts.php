<?php $view->extends('main'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
/*
 * drafts — страница черновиков пользователя (DR-02, DR-03, DR-08).
 *
 * КОНТРАКТ С БЭКОМ (фронт-каркас готов; роут и контроллер добавит бэк —
 * config/routes.php и app/ для фронта заморожены):
 *
 *   GET /drafts → роут drafts, AuthPolicy::Auth
 *        рендерит эту вьюху, передавая:
 *          $user  — UserContext текущего пользователя (сайдбар);
 *          $drafts — список черновиков СОЗДАННЫХ ТЕКУЩИМ ПОЛЬЗОВАТЕЛЕМ
 *                    (книги и статьи со статусом draft); каждый элемент —
 *                    массив ИЛИ объект (Publication/Book/Article) с полями:
 *                      id        — int
 *                      type      — 'book' | 'article'
 *                                  (если объект — определяется по классу)
 *                      title     — string
 *                      icon      — Uuid|string обложки (как Publication->iconId)
 *                                  или готовый URL в 'cover'/'iconUrl'
 *                      updatedAt — DateTimeInterface|string (приоритет)
 *                      createdAt — DateTimeInterface|string (фолбэк даты)
 *          $meta  — ['page' => int, 'pageSize' => int, 'hasNext' => bool]
 *                   (как у BooksController::savedList — пагинация).
 *
 *        Ссылки «Продолжить» ведут на /books/{id}/edit и /articles/{id}/edit —
 *        этих роутов пока нет (см. card-draft); «Открыть» — на существующие
 *        /books/{id} и /articles/{id}.
 *
 *   Локально страницу можно открыть через тест-роут:
 *        /pages/test?page=drafts/drafts
 *
 * Стили — public/assets/css/drafts.css (.card-draft*).
 * Карточка — src/partials/card-draft.php (DR-03).
 * JS не нужен: только ссылки и пагинация.
 */

$view->setBlock('selectedTab', 'drafts');

$drafts = $drafts ?? $items ?? [];
$meta   = $meta ?? [];

if (!is_array($drafts)) $drafts = [];

$page     = max(1, (int) ($meta['page'] ?? 1));
$pageSize = (int) ($meta['pageSize'] ?? 0);
$hasNext  = (bool) ($meta['hasNext'] ?? false);

$coversBase = '/uploads/covers/';

/**
 * Дата элемента: updatedAt приоритетнее createdAt; строка проходит как есть.
 * Формат d.m.Y — как на book-details / article-details.
 */
$draftDate = static function (object|array $item): string {
    foreach (['updatedAt', 'createdAt'] as $key) {
        $raw = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
        if ($raw instanceof \DateTimeInterface) return $raw->format('d.m.Y');
        if (is_string($raw) && trim($raw) !== '') return trim($raw);
    }
    return '';
};

/** Тип: явное поле type → имя класса модели → 'book'. */
$draftType = static function (object|array $item): string {
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
    return 'book';
};

/** Значение поля: массив или объект — читаем одинаково. */
$pick = static function (object|array $item, array $keys, string $default = '') use ($coversBase): string {
    foreach ($keys as $key) {
        $raw = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
        if ($raw instanceof \Stringable) $raw = (string) $raw;
        if ($raw === null || $raw === '') continue;
        if (is_string($raw)) {
            // UUID обложки (как Publication->iconId) -> путь загрузки
            if ($key === 'icon' || $key === 'iconId') return $coversBase . $raw;
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
        'type'  => $draftType($one),
        'title' => $pick($one, ['title'], ''),
        'cover' => $pick($one, ['cover', 'iconUrl', 'icon', 'iconId']),
        'date'  => $draftDate($one),
    ];
}

/**
 * Локаль и адрес страницы для ссылок пагинации: путь берём из URI
 * (роута /drafts ещё нет — страницу открывают и через /pages/test).
 */
$currentLang = $_GET['lang'] ?? null;
if (!is_string($currentLang) || $currentLang === '') {
    $currentLang = method_exists($view, 'locale') ? $view->locale() : null;
}

$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

$pageLink = static function (int $to) use ($currentPath, $currentLang): string {
    parse_str((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_QUERY), $q);
    if ($to > 1) $q['page'] = $to; else unset($q['page']);
    if ($currentLang !== null && $currentLang !== '') $q['lang'] = $currentLang;
    $qs = http_build_query($q);
    return $currentPath . ($qs !== '' ? '?' . $qs : '');
};

$shown = ($page - 1) * $pageSize + count($cards);
?>

<?php $view->startBlock('title'); ?><?= $tr('common.drafts.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/drafts.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.drafts.title'),
    'subtitle' => $view->t('common.drafts.subtitle'),
]); ?>

<?php if (empty($cards)): ?>

  <!-- ============================================================
       ПУСТОЕ СОСТОЯНИЕ (DR-08): ни одной строки от бэка
       ============================================================ -->
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
         href="<?= $view->e('/books/create' . (($currentLang !== null && $currentLang !== '') ? '?lang=' . urlencode((string) $currentLang) : '')) ?>">
        <?= $tr('common.drafts.empty_link') ?>
      </a>
    </div>
  </section>

<?php else: ?>

  <section class="books-panel drafts-panel" data-drafts-page>

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
            <span data-drafts-count><?= $shown ?></span><?= $hasNext ? '+' : '' ?>
            <?= $tr('common.drafts.count_suffix') ?>
          </p>
        </div>
      </div>
    </header>

    <?php if (empty($cards)): ?>
      <!-- Страница в есть, но на ней пусто (пагинация увела дальше) -->
      <p class="empty-state__text"><?= $tr('common.drafts.none_page') ?></p>
    <?php else: ?>
      <div class="grid-books drafts-grid">
        <?php foreach ($cards as $card): ?>
          <?php $view->include('card-draft', $card); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($page > 1 || $hasNext): ?>
      <nav class="books-panel__pager" aria-label="<?= $tr('common.drafts.pagination') ?>">
        <?php if ($page > 1): ?>
          <a class="btn btn--secondary" href="<?= $view->e($pageLink($page - 1)) ?>">
            <?= $tr('common.drafts.prev') ?>
          </a>
        <?php endif; ?>
        <?php if ($hasNext): ?>
          <a class="btn btn--secondary" href="<?= $view->e($pageLink($page + 1)) ?>">
            <?= $tr('common.drafts.next') ?>
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  </section>

<?php endif; ?>

<?php $view->endBlock('content'); ?>
