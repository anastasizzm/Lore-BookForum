<?php $view->extends('main'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
/*
 * drafts — страница черновиков пользователя (DR-02, DR-03, DR-08).
 *
 * КОНТРАКТ С БЭКОМ (config/ и app/ для фронта заморожены):
 *
 *   GET /books/drafts    → BooksController::draftList   (meta.type = 'book')
 *   GET /articles/drafts → ArticlesController::draftList (meta.type = 'article')
 *        оба рендерят ЭТУ же вьюху, передавая:
 *          $user  — UserContext текущего пользователя (сайдбар);
 *          $items — список черновиков: каждый элемент — массив ИЛИ объект
 *                    (Publication/Book/Article) с полями:
 *                      id        — int
 *                      type      — 'book' | 'article'
 *                                  (если объект — определяется по классу)
 *                      title     — string
 *                      icon      — Uuid|string обложки (как Publication->iconId)
 *                                  или готовый URL в 'cover'/'iconUrl'
 *                      creator   — UserShortData (properties=creator): нужен,
 *                                  чтобы отличить СВОИ черновики от чужих
 *                      updatedAt — DateTimeInterface|string (приоритет)
 *                      createdAt — DateTimeInterface|string (фолбэк даты)
 *          $meta  — ['page' => int, 'pageSize' => int, 'hasNext' => bool,
 *                    'type' => 'book'|'article'] (пагинация + раздел);
 *          $filterState — ?f= (панель фильтров читает сама).
 *
 *   ФИЛЬТРЫ — тот же partial, что в библиотеке (library-filters), но в режиме
 *   $draftMode: без вкладок публикации (бэк всегда шлёт status=draft) и без
 *   статуса ЧТЕНИЯ (контекст читателя). Пилюля «Книги/Статьи» меняет href на
 *   /books/drafts ↔ /articles/drafts. Работают: ?q=, ?genre=, ?isbn= (книги),
 *   ?type= и ?doi= (статьи) — рендер серверный, фильтр — переходом по ссылке.
 *   Контракт fromDraft — app/Models/Filters/Publications/*Filters.php.
 *
 *   ДЕЙСТВИЯ С КАРТОЧКИ (drafts.js):
 *     Удалить   — ConfirmModal → DELETE /api/{books|articles}/{id} → карточка
 *                 уходит из списка (без редиректа);
 *     Публикация — через окно ролей: книги RightsModal.open({mode:'publish'}),
 *                 статьи LoreArticlePublish.open(id); 409 (нет контента) —
 *                 тост errors.books.content_pdf / errors.article.publish_no_content.
 *
 *   Ссылка «Продолжить»: книги → /books/new?id={id} (форма правки, book-form.js),
 *   статьи → /articles/{id}/edit (роута пока нет — см. card-draft).
 *   «Открыть» → /books/{id} или /articles/{id}.
 *
 * Стили — public/assets/css/drafts.css (.card-draft*).
 * Карточка — src/partials/card-draft.php (DR-03).
 * JS — public/assets/js/drafts.js (удаление/публикация/сброс фильтров).
 */

$view->setBlock('selectedTab', 'drafts');

$drafts = $drafts ?? $items ?? [];
$meta   = $meta ?? [];

if (!is_array($drafts)) $drafts = [];

$page     = max(1, (int) ($meta['page'] ?? 1));
$pageSize = (int) ($meta['pageSize'] ?? 0);
$hasNext  = (bool) ($meta['hasNext'] ?? false);

// Раздел: контроллер пишет meta.type; иначе — по пути (страницу можно
// открыть и через тест-роут /pages/test).
$currentPath  = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$isArticles   = ($meta['type'] ?? null) === 'article';
if (($meta['type'] ?? null) === null) {
    $isArticles = str_contains($currentPath, '/articles');
}

// Текущий пользователь — чтобы пометить СВОИ карточки (data-requires-owner):
// публикация/удаление чужого черновика бэк не пропустит, кнопки на чужих
// карточках гасит permissions.js.
$currentUserId = (int) ($user->id ?? 0);

// Активные фильтры: при них «нет ни одного черновика» (DR-08) не показываем —
// вместо него отрисуется «на этой странице пусто» + панель фильтров.
$hasActiveFilters = false;
foreach (['q', 'genre', 'isbn', 'doi', 'type', 'kind'] as $fk) {
    if (isset($_GET[$fk]) && is_scalar($_GET[$fk]) && trim((string) $_GET[$fk]) !== '') {
        $hasActiveFilters = true;
        break;
    }
}

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

    // Создатель (properties=creator): data-requires-owner в card-draft —
    // кнопки «Опубликовать»/«Удалить» показывает permissions.js только на
    // СВОИХ карточках (или когда правила бэка дают право глобально).
    $creatorRaw = is_array($one) ? ($one['creator'] ?? null) : ($one->creator ?? null);
    $creatorId  = (int) (is_array($creatorRaw) ? ($creatorRaw['id'] ?? 0)
                        : (is_object($creatorRaw) ? ($creatorRaw->id ?? 0) : 0));

    $cards[] = [
        'id'    => $id,
        'type'  => $draftType($one),
        'title' => $pick($one, ['title'], ''),
        'cover' => $pick($one, ['cover', 'iconUrl', 'icon', 'iconId']),
        'date'  => $draftDate($one),
        'owner' => $currentUserId > 0 && $creatorId > 0 && $creatorId === $currentUserId,
    ];
}

/**
 * Локаль и адрес страницы для ссылок пагинации: путь берём из URI
 * ($currentPath посчитан выше).
 */
$currentLang = $_GET['lang'] ?? null;
if (!is_string($currentLang) || $currentLang === '') {
    $currentLang = method_exists($view, 'locale') ? $view->locale() : null;
}

$pageLink = static function (int $to) use ($currentPath, $currentLang): string {
    parse_str((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_QUERY), $q);
    if ($to > 1) $q['page'] = $to; else unset($q['page']);
    if ($currentLang !== null && $currentLang !== '') $q['lang'] = $currentLang;
    $qs = http_build_query($q);
    return $currentPath . ($qs !== '' ? '?' . $qs : '');
};

$shown = ($page - 1) * $pageSize + count($cards);

/** Совсем нет черновиков (а не «страница пагинации опустела»). При активных
 *  фильтрах это «ничего не нашлось» — тогда показываем сетку + панель. */
$isEmptyAll = empty($cards) && $page <= 1 && !$hasActiveFilters && !$hasNext;
?>

<?php $view->startBlock('title'); ?><?= $tr('common.drafts.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/drafts.css') ?>">
  <!-- Окно публикации, которое рендерит этот раздел (см. конец content) -->
  <link rel="stylesheet" href="<?= $view->asset('css/roles-module.css') ?>">
  <?php if ($isArticles): ?>
    <link rel="stylesheet" href="<?= $view->asset('css/article-right.css') ?>">
  <?php else: ?>
    <link rel="stylesheet" href="<?= $view->asset('css/rights-modal.css') ?>">
  <?php endif; ?>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php
// Поиск + кнопка фильтров в шапке (как в библиотеке): ввод ?q= применяется
// по Enter (filters.js), кнопка раскрывает панель (?f=).
ob_start();
$view->include('input', [
    'type'        => 'search',
    'name'        => 'q',
    'placeholder' => $view->t($isArticles ? 'common.library.search_articles' : 'common.library.search_books'),
    'value'       => isset($_GET['q']) && is_scalar($_GET['q']) ? (string) $_GET['q'] : '',
]);
?>
<button type="button"
        class="btn-icon filter-toggle"
        data-filter-toggle
        aria-label="<?= $tr('common.common.filters') ?>">
  <span>&#9776;</span>
</button>
<?php $pageActions = ob_get_clean(); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.drafts.title'),
    'subtitle' => $view->t('common.drafts.subtitle'),
    'actions'  => $pageActions,
]); ?>

<?php
// Панель фильтров — та же, что в библиотеке, но в режиме черновиков:
// без статусов публикации/чтения; пилюля Книги/Статьи ведёт на соседний
// роут черновиков (/books/drafts ↔ /articles/drafts).
$view->include('library-filters', [
    'bookHref'    => '/books/drafts',
    'articleHref' => '/articles/drafts',
    'draftMode'   => true,
]);
?>

<?php // Скрытый токен CSRF — его читает csrf.js (запасной вариант к куке) ?>
<div hidden data-csrf><?= $view->csrfField() ?></div>

<?php if ($isEmptyAll): ?>

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
         href="<?= $view->e('/books/new' . (($currentLang !== null && $currentLang !== '') ? '?lang=' . urlencode((string) $currentLang) : '')) ?>">
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
      <!-- Страница есть, но на ней пусто (пагинация увела дальше): ниже
           остаётся pager, чтобы вернуться назад -->
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

  <!-- Окна публикации для кнопки на карточке (drafts.js): книги —
       rights-modal + модуль ролей (POST /api/books/{id}/publish), статьи —
       article-rights-dialog (POST /api/articles/{id}/publish). На странице
       только ОДНО окно: разделы живут на разных роутах (/books/drafts ↔
       /articles/drafts), а roles-module.php содержит id — дважды на странице
       они бы задублировались. -->
  <?php if ($isArticles): ?>
    <?php $view->include('article-rights-dialog'); ?>
  <?php else: ?>
    <?php $view->include('rights-modal'); ?>
  <?php endif; ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <?php if ($isArticles): ?>
    <!-- publication-roles.js — окно статей подтягивает список ролей
         (window.LorePubRoles, см. article-publish.js) -->
    <script src="<?= $view->asset('js/publication-roles.js') ?>"></script>
    <script src="<?= $view->asset('js/article-publish.js') ?>"></script>
  <?php else: ?>
    <script src="<?= $view->asset('js/rights-modal.js') ?>"></script>
  <?php endif; ?>
  <!-- roles-module.js — модуль «назначение ролей» внутри окна публикации
       (window.LoreRoles; подключается после самого окна) -->
  <script src="<?= $view->asset('js/roles-module.js') ?>" defer></script>
  <!-- drafts.js — удаление и публикация черновика из списка, сброс фильтров -->
  <script src="<?= $view->asset('js/drafts.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>