<?php
/**
 * card-book - vertical book card.
 *
 * Используется:
 *   - SSR-списком library-list.php (передаёт $item, $detailBase, $isArticles);
 *   - старыми местами, где передаются плоские $id/$cover/$title/...
 *
 * Поддерживает оба варианта: если передан $item — читаем из него,
 * иначе берём плоские переменные (обратная совместимость).
 */

$get = static function ($src, string $key, $default = null) {
    if ($src === null) return $default;
    if (is_array($src)) return $src[$key] ?? $default;
    if (is_object($src)) {
        if (isset($src->$key)) return $src->$key;
        if ($src instanceof \ArrayAccess && $src->offsetExists($key)) return $src[$key];
    }
    return $default;
};

// --- Источник данных: $item (новый SSR) или плоские переменные (старый) ---
$item       = $item       ?? null;
$isArticles = (bool) ($isArticles ?? false);
$detailBase = (string) ($detailBase ?? ($isArticles ? '/articles' : '/books'));

if ($item !== null) {
    $id     = (int) $get($item, 'id', 0);
    $title  = (string) $get($item, 'title', '');

    // Обложка: cover / iconUrl / iconId (Uuid-объект или строка)
    $cover = (string) $get($item, 'cover', '');
    if ($cover === '') {
        $cover = (string) $get($item, 'iconUrl', '');
    }
    if ($cover === '') {
        $icon = $get($item, 'iconId');
        $uuid = is_object($icon) ? ($icon->uuid ?? $icon->value ?? '') : (string) $icon;
        if ($uuid !== '') $cover = '/uploads/' . $uuid;
    }

    // Автор
    $creator    = $get($item, 'creator') ?? $get($item, 'author');
    $authorName = '';
    $authorId   = 0;
    if ($creator) {
        $name = trim((string) $get($creator, 'name', '') . ' ' . (string) $get($creator, 'surname', ''));
        $authorName = $name !== '' ? $name : (string) $get($creator, 'username', '');
        $authorId   = (int) $get($creator, 'id', 0);
    }

    $saved = (bool) ($get($item, 'isSaved') ?? $get($item, 'saved') ?? false);

    // Статус чтения
    $rsRaw = $get($item, 'readingStatus');
    if (is_object($rsRaw)) $rsRaw = $rsRaw->value ?? null;
    $readingStatus = $isArticles ? 'none' : (string) ($rsRaw ?: 'none');

    // Бейдж «Draft» — только если активен фильтр ?p_status=draft
    $pubStatus = (($_GET['p_status'] ?? '') === 'draft') ? 'draft' : '';

} else {
    // Старый плоский интерфейс
    $id            = (int)    ($id            ?? 0);
    $cover         = (string) ($cover         ?? '');
    $title         = (string) ($title         ?? '');
    $authorId      = (int)    ($authorId      ?? 0);
    $authorName    = (string) ($author        ?? '');
    $saved         = (bool)   ($saved         ?? false);
    $readingStatus = (string) ($readingStatus ?? 'none');
    $pubStatus     = (string) ($pubStatus     ?? '');
}

// --- Тип публикации для Save ---
$saveType  = $isArticles ? 'article' : 'book';
$isArticle = $isArticles;
$saveAttr  = $isArticle ? 'data-save-article' : 'data-save-book';
$idAttr    = $isArticle ? 'data-article-id'   : 'data-book-id';
$saveLabel = $saved ? 'Remove from saved' : 'Save ' . $saveType;

$hasCover = $cover !== '';

// --- Ссылки ---
$bookUrl = $id > 0
    ? $detailBase . '/' . $id
    : '#';

$authorUrl = $authorId > 0 ? '/users/' . $authorId : '#';

// --- Бейдж статуса чтения ---
$statusLabel = match ($readingStatus) {
    'reading' => $view->t('common.filter.reading'),
    'ended'   => $view->t('common.filter.finished'),
    default   => '',
};

// --- Бейдж «Черновик» ---
$isDraft = (string) $pubStatus === 'draft';
?>
<article class="card-base card-book">

  <div class="card-book__cover<?= $hasCover ? '' : ' cover--empty' ?>">
    <?php if ($hasCover): ?>
      <img src="<?= $view->e($cover) ?>"
           alt="<?= $view->e($title) ?>"
           loading="lazy">
    <?php endif; ?>
    <?php if ($statusLabel !== ''): ?>
      <span class="card-book__status card-book__status--<?= $view->e($readingStatus) ?>"><?= $view->e($statusLabel) ?></span>
    <?php endif; ?>
    <?php if ($isDraft): ?>
      <span class="card-book__status card-book__status--draft"><?= $view->e($view->t('common.drafts.badge')) ?></span>
    <?php endif; ?>
  </div>

  <button type="button"
          class="btn-icon btn-icon--circle card-book__save<?= $saved ? ' is-active' : '' ?>"
          <?= $saveAttr ?>
          <?= $idAttr ?>="<?= $id ?>"
          data-save-url="<?= $view->e(($isArticle ? '/api/articles' : '/api/books') . '/' . $id . '/save') ?>"
          aria-pressed="<?= $saved ? 'true' : 'false' ?>"
          aria-label="<?= $view->e($saveLabel) ?>">
    <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
    </svg>
  </button>

  <h3 class="card-book__title">
    <a class="card-book__link"
       href="<?= $view->e($bookUrl) ?>"
       aria-label="Open <?= $view->e($saveType) ?>: <?= $view->e($title) ?>">
      <?= $view->e($title) ?>
    </a>
  </h3>

  <?php if ($authorName !== ''): ?>
    <p class="card-book__author">
      <a class="card-book__author-link"
         href="<?= $view->e($authorUrl) ?>"
         aria-label="Open author profile: <?= $view->e($authorName) ?>">
        <?= $view->e($authorName) ?>
      </a>
    </p>
  <?php endif; ?>

</article>