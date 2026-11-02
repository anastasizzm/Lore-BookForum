<?php
/**
 * article-details — страница статьи (по аналогии с book/book-details.php).
 *
 * Шапка страницы содержит меню «три точки» (details-menu.php): пункты
 * «Редактировать» (ссылка на /articles/{id}/edit) и «Удалить»
 * (DELETE /api/articles/{id}, details-menu.js) — оба рендерятся скрытыми
 * (hidden) и показываются permissions.js по data-requires;
 * контракт обоих роутов — в шапке partial.
 *
 * ЧЕРНОВИК / ПУБЛИКАЦИЯ (по образцу book-details):
 *   $status   — контроллер может передать напрямую ('draft' | 'published');
 *               иначе вьюха читает поле модели. Бэкенд статуса статьи
 *               пока не отдаёт — до тех пор страница не считает статью
 *               черновиком и publish-bar не показывает.
 *   Статус draft: в шапке бейдж «Черновик», внизу страницы publish-bar
 *               с кнопкой «Опубликовать статью» (hidden по умолчанию,
 *               показывает permissions.js по data-requires="publication.publish");
 *               кликом открывается article-rights-dialog (article-publish.js,
 *               контракт POST /api/articles/{id}/publish — в partial).
 *
 * Ожидаемые переменные от контроллера:
 *   $article — объект App\Models\Publications\Article
 *   $comments — массив постов (те же поля, что в feed-list.php);
 *               контроллер его НЕ передаёт — book.js подгружает комментарии
 *               сам из GET /api/posts?publication={id}&include=creator (P0-5)
 *   $user    — App\Models\Users\UserContext текущего пользователя
 *              (имя/инициалы/аватар в форме комментария и в шаблоне ответа)
 *
 * Поля, которых может не быть в модели (rating, savesCount, isSaved), читаются
 * через ?? — чтобы страница не падала. Объекты (genre, creator, book) приводятся
 * к строке через хелпер $str.
 */

/**
 * Контроллер отдаёт 'wrapper' => WithContext<Article, PublicationContext>
 * (ArticlesController::retrieve): item — сама статья, context — данные текущего
 * юзера (isSaved / isEditor / ...). Старое имя 'article' поддерживаем.
 */
$wrapper   = $wrapper ?? null;
$article   = $article ?? null;
$readerCtx = null;

if ($article === null && $wrapper !== null) {
    if ($wrapper instanceof \App\Models\UserContext\WithContext) {
        $article   = $wrapper->item;
        $readerCtx = $wrapper->context;
    } else {
        $article = $wrapper;
    }
}
if ($article === null) {
    return;
}

// Короткий помощник: перевод + экранирование (ключи — resources/lang/*/common.json)
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

/* ---------- Универсальное приведение к строке ---------- */
$str = static function ($v): string {
    if ($v === null) return '';
    if (is_string($v)) return $v;
    if (is_scalar($v)) return (string) $v;
    if ($v instanceof \BackedEnum) return (string) $v->value;
    if (is_object($v)) {
        if (method_exists($v, '__toString')) return (string) $v;
        if (isset($v->title)) return (string) $v->title;
        if (isset($v->name))  return (string) $v->name;
    }
    return '';
};

/* ---------- Скалярные значения ---------- */

// id статьи: и из объекта, и из массива (тест-роут /pages/test отдаёт
// query-массивы — от этого зависит data-publication-id у комментариев
// и data-rate-publication у звёзд оценки)
$articleRawId  = is_array($article) ? ($article['id'] ?? null) : ($article->id ?? null);
$publicationId = (int) $articleRawId;
$articleId     = (int) $articleRawId;
// isSaved живёт в контексте юзера (PublicationContext), а не в модели
$isSaved       = (bool) ($readerCtx?->isSaved ?? $article->isSaved ?? false);
$savesCount    = (int)  ($article->savedCount ?? 0);

// rating_avg в БД хранится умноженным на 10 (46 -> 4.6);0 — оценок нет.
// Деление всегда: модель отдаёт ровно контрактные ×10 (int 0..50),
// эвристика «> 5» ломалась на значениях 1..5 (среднее 0.1..0.5).
// Читаем и объект (прод), и query-массив (тест-роут /pages/test).
$ratingRaw = (float) (is_array($article) ? ($article['rating'] ?? 0) : ($article->rating ?? 0));
$rating = $ratingRaw / 10;
if ($rating > 5) $rating = 5;
if ($rating < 0) $rating = 0;
$hasRating = $ratingRaw > 0;

$percent = number_format(max(0, min(100, $rating / 5 * 100)), 2, '.', '');

/* ---------- Даты ---------- */

$createdAt = '';
if (($article->createdAt ?? null) instanceof \DateTimeInterface) {
    $createdAt = $article->createdAt->format('d.m.Y');
} elseif (is_string($article->createdAt ?? null)) {
    $createdAt = $article->createdAt;
}

/* ---------- Автор ---------- */

$authorName = '';
if (($article->creator ?? null) !== null) {
    $authorName = trim(($article->creator->name ?? '') . ' ' . ($article->creator->surname ?? ''));
    if ($authorName === '') {
        $authorName = (string) ($article->creator->username ?? '');
    }
}

/* ---------- Владелец публикации (для кнопок Edit/Delete/Publish) ----------
 * Как в book-details: глобальные /api/publications/rules owner-override не
 * отдают, поэтому «это моя статья» считаем по creator.id и отдаём в
 * details-menu как $isOwner — там он рендерится в data-requires-owner,
 * и permissions.js складывает признак с правилами.
 */
$creatorObj = is_object($article) ? ($article->creator ?? null) : null;
$creatorId  = is_object($creatorObj) ? (int) ($creatorObj->id ?? 0) : 0;
$viewerId   = (int) ($currentUserId ?? 0);
$isOwnerPub = $viewerId > 0 && $creatorId > 0 && $viewerId === $creatorId;

/* ---------- Статус публикации (бейдж «Черновик» + publish-bar) ----------
 * Приоритет — явный $status от контроллера, иначе поле модели
 * (как в book-details). Бэкенд статусы статей пока не отдаёт:
 * до тех пор $isDraft = false и плашка публикации не показывается.
 * Читаем и объект (прод), и query-массив (тест-роут /pages/test).
 */
$statusRaw = $status ?? (is_array($article) ? ($article['status'] ?? null)
                                            : ($article->status ?? null));
$status    = is_string($statusRaw) ? strtolower(trim($statusRaw)) : '';
$isDraft   = $status === 'draft';

/* ---------- Жанр / DOI ---------- */

$genreTitle = $str($article->genre ?? null);
// DOI — колонка articles.doi (ArticlesScriptDirector выбирает её явно);
// объект (прод) или query-массив (тест-роут).
$doi        = $str(is_array($article) ? ($article['doi'] ?? null) : ($article->doi ?? null));

/* ---------- Тип статьи / книга-источник ---------- */
/**
 * Структурный тип лежит в contentData, а не в ->type (->type — это «жанр» статьи
 * из таблицы types: Article / Review / …). Отрывок из книги = contentData
 * содержит bookData — отсюда же берём id книги для ссылки «Open book» (P1-6).
 */
$contentData = $article->contentData ?? null;
$bookData    = $contentData?->getBookData();
$bookId      = (int) ($bookData?->bookId ?? 0);
$isBookExcerpt = $bookData !== null;

/* ---------- Аннотация / заметка / контент ---------- */
// В модели поля называются description / authorNotes (не annotation / authorNote).

$annotation = (string) ($article->description ?? '');
$authorNote = (string) ($article->authorNotes ?? '');
$content    = (string) ($contentData?->getContent() ?? '');

/* ---------- Книга-источник (для отрывка) ---------- */

$bookTitle = $str($bookData?->book ?? null);

$pageStart = $bookData?->pageStart ?? null;
$pageEnd   = $bookData?->pageEnd   ?? ($pageStart ?? null);

/* ---------- Комментарии ---------- */
/**
 * Список комментариев контроллер не передаёт (P0-5): book.js подгружает их
 * сам из GET /api/posts?publication={id}&include=creator.
 */
$comments      = $comments ?? [];
$totalComments = (int) ($totalComments ?? ($article->commentsCount ?? count($comments)));

/* ---------- Текущий пользователь (форма комментария + шаблон ответа) ---------- */

$me         = $user ?? null;
$meName     = trim(($me->name ?? '') . ' ' . ($me->surname ?? ''));
if ($meName === '') {
    $meName = (string) ($me->username ?? '');
}
if ($meName === '') {
    $meName = 'sername'; // последний запасной вариант
}
$meParts    = preg_split('/\s+/u', $meName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$meInitials = mb_strtoupper(mb_substr($meParts[0] ?? 'm', 0, 1) . mb_substr($meParts[1] ?? '', 0, 1));
if ($meInitials === '') {
    $meInitials = 'ME';
}
// Ключ аватара ('cat', 'fox', 'default'...). Как его показать — решает partial avatar.php.
$meAvatar = (string) ($me->avatar ?? '');

/* ---------- Обложка ---------- */

// Нет обложки — пустая строка: вьюха рисует .cover--empty (готовый
// data-URI-вектор из component.css, тот же, что в library/saved).
// iconId читаем и как объект (прод), и как query-массив (тест-роут /pages/test).
$iconId = is_array($article) ? ($article['iconId'] ?? null) : ($article->iconId ?? null);
$coverUrl = '';
if (!empty($iconId)) {
    if ($iconId instanceof \Stringable) {
        $coverUrl = '/uploads/covers/' . (string) $iconId;
    } elseif (is_string($iconId)) {
        $coverUrl = '/uploads/covers/' . $iconId;
    }
}
?>
<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'library'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.article.page_title') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/book.css">
  <!-- publish-bar и бейдж черновика — стили book.css (подключён выше);
       article-right.css — окно выбора прав при публикации статьи -->
  <link rel="stylesheet" href="/assets/css/article-right.css">
  <!-- модуль «назначение ролей» внутри окна публикации -->
  <link rel="stylesheet" href="/assets/css/roles-module.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <div class="book-page-header">
    <h1 class="book-page-title"><?= $tr('common.article.page_title') ?></h1>
    <?php if ($isDraft): ?>
      <!-- Метка черновика: статья ещё не опубликована -->
      <span class="book-page-header__badge"
            title="<?= $tr('common.article.draft_hint') ?>"><?= $tr('common.article.draft_badge') ?></span>
    <?php endif; ?>
    <!-- Меню «три точки» справа в шапке: «Редактировать» и «Удалить» —
         по образцу book-details. Пункты рендерятся скрытыми, показывает
         permissions.js по правилам /api/publications/rules, $isOwnerPub —
         owner-override (data-requires-owner). -->
    <?php $view->include('details-menu', [
        'type'    => 'article',
        'id'      => $articleId,
        'isOwner' => $isOwnerPub,
        // Название — подпись цели в плашке «Пожаловаться» (report-modal.php)
        'title'   => (string) ($article->title ?? ''),
    ]); ?>
  </div>

  <div class="book-details">
    <div class="book-details__cover-col">
      <?php if ($coverUrl === ''): ?>
        <!-- Обложки нет: .cover--empty — data-URI-иконка книжки
             из component.css (как в library/saved), <img> не нужен -->
        <div class="book-details__cover cover--empty" aria-hidden="true"></div>
      <?php else: ?>
        <!-- Битую загрузку (404) пометит cover--empty app.js — той же
             логикой пользуются карточки library/saved -->
        <div class="book-details__cover">
          <img src="<?= $view->e($coverUrl) ?>"
               alt="<?= $view->e((string) ($article->title ?? '')) ?>">
        </div>
      <?php endif; ?>

      <div class="book-actions">
        <button type="button"
                class="btn-icon btn-icon--circle<?= $isSaved ? ' is-active' : '' ?>"
                data-save-article
                data-article-id="<?= $articleId ?>"
                data-save-url="/api/articles/<?= $articleId ?>/save"
                aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                aria-label="<?= $isSaved ? $tr('common.book.unsave') : $tr('common.article.save') ?>">
          <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </button>
        <?php
          // P1-6: «Open book» ведёт на страницу книги, а не на «#»
          $bookHref = ($isBookExcerpt && $bookId > 0)
              ? '/books/' . $bookId
              : '#annotation';
        ?>
        <a class="btn btn--primary btn--pill" href="<?= $view->e($bookHref) ?>">
          <?= $isBookExcerpt ? $tr('common.article.open_book') : $tr('common.article.read') ?>
        </a>
      </div>

      <div class="book-rating">
        <div class="book-rating__group">
          <span class="book-rating__stars"
                data-rating-stars
                style="--rating-percent: <?= $view->e($percent) ?>%;"
                role="img"
                aria-label="<?= $hasRating
                    ? $tr('common.book.rating_aria', ['value' => number_format($rating, 1)])
                    : $tr('common.book.no_rating') ?>">
            ★★★★★
          </span>
          <?php if ($hasRating): ?>
            <!-- Дробный балл из rating_avg ×10: 46 -> 4.6, без округления -->
            <span class="book-rating__value" data-rating-value><?= $view->e(number_format($rating, 1)) ?></span>
          <?php else: ?>
            <!-- Оценок нет — серая подпись вместо «0.0» -->
            <span class="book-rating__value book-rating__value--none" data-rating-value><?= $tr('common.book.no_rating') ?></span>
          <?php endif; ?>
        </div>
        <span class="book-rating__saves">
          <svg width="12" height="16" viewBox="0 0 12 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H10C10.5523 1 11 1.44772 11 2V14.5273C11 14.928 10.5574 15.1704 10.2039 14.9631L6 12.5L1.79612 14.9631C1.44265 15.1704 1 14.928 1 14.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
          <span data-saves-count><?= $savesCount ?></span>
        </span>
      </div>

      <div class="rate" data-rate role="radiogroup"
           data-rate-kind="articles"
           data-rate-publication="<?= (int) $publicationId ?>"
           data-rate-title="<?= $view->e((string) ($article->title ?? '')) ?>"
           data-rate-current="<?= (int) ($readerCtx?->rating ?? 0) ?>"
           aria-label="<?= $tr('common.article.rate_group') ?>">
        <span class="rate__label"><?= $tr('common.book.rate_label') ?></span>
        <div class="rate__stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button"
                    class="rate__star"
                    data-rate-value="<?= $i ?>"
                    role="radio"
                    aria-checked="false"
                    aria-label="<?= $tr('common.book.rate_star', ['value' => $i]) ?>">
              <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2.5l2.94 5.96 6.56.95-4.75 4.63 1.12 6.54L12 17.5l-5.87 3.08 1.12-6.54L2.5 9.41l6.56-.95L12 2.5z"/>
              </svg>
            </button>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="card-base info-box">
      <h2 class="info-box__title"><?= $view->e($article->title ?? '') ?></h2>
      <p class="info-box__meta"><?= $view->e($authorName) ?></p>
      <p class="info-box__meta"><?= $tr('common.book.created', ['value' => $createdAt]) ?></p>
      <?php if ($genreTitle !== ''): ?>
        <p class="info-box__meta"><?= $tr('common.book.genre', ['value' => $genreTitle]) ?></p>
      <?php endif; ?>

      <?php if ($isBookExcerpt): ?>
        <p class="info-box__meta"><?= $tr('common.article.type_excerpt') ?></p>
        <?php if ($bookTitle !== ''): ?>
          <p class="info-box__meta">
            <?= $tr('common.article.book_line', ['value' => $bookTitle]) ?>
            <?php if (!empty($pageStart)): ?>
              <?= $tr('common.article.pages_short', ['from' => (int) $pageStart, 'to' => (int) $pageEnd]) ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <p class="info-box__meta"><?= $tr('common.article.type_standalone') ?></p>
      <?php endif; ?>

      <?php /* P0-3: Type идёт выше DOI — как в макете */ ?>
      <?php if ($doi !== ''): ?>
        <p class="info-box__meta"><?= $tr('common.article.doi', ['value' => $doi]) ?></p>
      <?php endif; ?>

      <div class="book-tabs-panel">
        <?php
          $view->include('tabs', [
              'variant' => 'outline',
              'items'   => [
                  ['label' => $view->t('common.book.tab_annotation'), 'href' => '#annotation', 'active' => true, 'row' => 'annotation'],
                  ['label' => $isBookExcerpt ? $view->t('common.article.tab_excerpt') : $view->t('common.article.tab_content'), 'href' => '#content', 'active' => false, 'row' => 'content'],
              ],
          ]);
        ?>

        <div class="card-base book-tabs-panel__content" data-row="annotation">
          <?php if ($annotation !== ''): ?>
            <p class="info-box__body" id="annotation"><?= nl2br($view->e($annotation)) ?></p>
          <?php else: ?>
            <p class="info-box__body" id="annotation"><?= $tr('common.book.no_annotation') ?></p>
          <?php endif; ?>

          <?php if ($authorNote !== ''): ?>
            <div class="book-tabs-panel__note">
              <strong><?= $tr('common.book.author_note') ?></strong><br>
              <?= nl2br($view->e($authorNote)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card-base book-tabs-panel__content" data-row="content" hidden>
          <?php if ($isBookExcerpt): ?>
            <p class="info-box__body">
              <?= $tr('common.article.excerpt_intro') ?>
              <strong><?= $bookTitle !== '' ? $view->e($bookTitle) : $tr('common.article.untitled') ?></strong><?php
                if (!empty($pageStart)):
                  ?><?= $tr('common.article.excerpt_pages', ['from' => (int) $pageStart, 'to' => (int) $pageEnd]) ?><?php
                endif;
              ?>.
            </p>
            <!-- P1-6: ссылка ведёт на страницу книги -->
            <p><a class="link" href="<?= $view->e($bookId > 0 ? '/books/' . $bookId : '#') ?>"><?= $tr('common.article.go_to_book') ?></a></p>
          <?php elseif ($content === ''): ?>
            <p class="info-box__body"><?= $tr('common.article.no_text') ?></p>
          <?php else: ?>
            <div class="info-box__body article-content">
              <?php foreach (preg_split('/\R{2,}/u', trim($content)) as $para): ?>
                <?php if (trim($para) === '') continue; ?>
                <p><?= nl2br($view->e(trim($para))) ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Блок комментариев.
       data-me-* нужен book.js: автора своего комментария/ответа он берёт из
       data-me-username (= creator.username в API), иначе после F5 имя «мигает».
       На статьях этих атрибутов раньше не было — оптимистичный ответ рисовался
       с пустым автором. -->
  <section class="comments-section" data-comments
           data-publication-id="<?= (int) $publicationId ?>"
           data-me-id="<?= (int) ($me->id ?? 0) ?>"
           data-me-name="<?= $view->e($meName) ?>"
           data-me-username="<?= $view->e((string) ($me->username ?? '')) ?>"
           data-me-initials="<?= $view->e($meInitials) ?>"
           data-me-avatar="<?= $view->e($meAvatar ?? '') ?>">
    <h2 class="comments-section__title">
      <?= $tr('common.comments.title') ?> <span data-comments-count><?= (int) $totalComments ?></span>
    </h2>

    <!--
      Форма комментария (стилизована как карточка).
      Контракт: POST /api/posts, application/x-www-form-urlencoded
        поля: content, publicationId, csrf-поле
        успех: любой 2xx без payload-а с ошибкой (201 {"createdId": N} — норма)
        ошибка: JSON с errors / message
      Отправку делает book.js (fetch): Enter в поле и кнопка-галочка ведут в одну
      отправку, обработчики висят на document (переживают позднюю отрисовку DOM).
    -->
    <form class="comment-card" action="/api/posts" method="POST" data-comment-form novalidate>
      <?= $view->csrfField() ?>
      <input type="hidden" name="publicationId" value="<?= (int) $publicationId ?>">
      <div class="comment-card__inner">
        <?php $view->include('avatar', [
            'size'     => 'sm',
            'initials' => $meInitials,
            'icon'     => $meAvatar,
            'src'      => is_string($meAvatar) && str_contains($meAvatar, '/') ? $meAvatar : null,
        ]); ?>
        <div class="comment-card__content">
          <div class="comment-card__author"><?= $view->e($meName) ?></div>
          <div class="comment-card__row">
            <input class="comment-card__input" type="text" name="content"
                   maxlength="2000" autocomplete="off"
                   placeholder="<?= $tr('common.comments.placeholder') ?>">
            <!-- Кнопка отправки (галочка): без неё Enter не выглядел «отправкой» (P0-2) -->
            <button type="submit" class="comment-card__send" data-comment-send disabled aria-label="<?= $tr('common.comments.send') ?>">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
          <p class="form-field__error" data-comment-error role="alert" hidden
             style="color: red; margin-top: 8px; font-size: 14px;"></p>
          <p data-comment-status role="status" hidden
             style="color: green; margin-top: 8px; font-size: 14px;"></p>
        </div>
      </div>
    </form>

    <p class="comments-section__empty" data-comments-empty<?= empty($comments) ? '' : ' hidden' ?>>
      <?= $tr('common.comments.first') ?>
    </p>

    <!-- Список комментариев: сервер его не передаёт, book.js наполняет из API (P0-5) -->
    <div class="stack" data-comment-list>
      <?php foreach ($comments as $comment): ?>
        <?php
          $cId       = (int) ($comment['id'] ?? 0);
          $cLikes    = (int) ($comment['likes'] ?? 0);
          $cLiked    = (bool) ($comment['liked'] ?? false);
          // Автор — нужен comments.js, чтобы «Удалить» в меню «три точки»
          // показывало только автору (само меню видно всем: там есть «Пожаловаться»)
          $cAuthorId = (int) ($comment['userId'] ?? 0);
        ?>
        <div class="comment-card" data-page="1"
             data-author-id="<?= $cAuthorId ?>"<?= $cId ? ' data-comment-id="' . $cId . '"' : '' ?>>
          <div class="comment-card__inner">
            <?php
            $cAvatar = (string) ($comment['userAvatar'] ?? '');
            $view->include('avatar', [
                'size'     => 'sm',
                'initials' => $comment['userInitials'] ?? 'SN',
                'icon'     => $cAvatar,
                'src'      => str_contains($cAvatar, '/') ? $cAvatar : null,
            ]);
            ?>

            <div class="comment-card__content">
              <!-- Текст слева, «три точки» справа (на месте бывшего сердечка) -->
              <div class="comment-card__main">
                <div class="comment-card__head">
                  <!-- Ник автора — без @: собачка только в упоминаниях внутри текста -->
                  <div class="comment-card__author"><?= $view->e($comment['userName']) ?></div>
                  <div class="comment-card__text"><?= nl2br($view->e($comment['text'])) ?></div>
                </div>

                <!-- Лайк уходит вниз (рядом с Reply); здесь — «три точки»:
                     удаление (только автор) + «Пожаловаться» (всем) -->
                <?php $view->include('comment-menu', ['variant' => 'comment']); ?>
              </div>

              <!-- Под текстом: сердечко + Reply слева, дата справа (под «тремя точками») -->
              <div class="comment-card__footer">
                <div class="comment-card__meta">
                  <!-- Лайк комментария: общий обработчик card-feed.js (POST/DELETE /api/posts/{id}/like) (P0-4) -->
                  <button type="button" class="btn-icon-small btn-like comment-card__like<?= $cLiked ? ' is-liked' : '' ?>"
                          data-like-btn
                          data-like-id="<?= $cId ?>"
                          data-liked="<?= $cLiked ? '1' : '0' ?>"
                          aria-pressed="<?= $cLiked ? 'true' : 'false' ?>" aria-label="<?= $tr('common.comments.like') ?>">
                    <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span data-like-count><?= $cLikes ?></span>
                  </button>
                  <button type="button" class="comment-card__reply" data-reply-toggle
                          aria-expanded="false" aria-label="<?= $tr('common.comments.reply') ?>"><?= $tr('common.comments.reply') ?></button>
                </div>
                <span class="comment-card__date"><?= $view->e($comment['date']) ?></span>
              </div>

              <!-- Ещё ниже: раскрытие ответов; book.js наполняет после загрузки -->
              <button type="button" class="comment-card__more" data-replies-toggle hidden></button>

              <form class="comment-reply-form" data-reply-form hidden>
                <input type="text" class="comment-reply-form__input" placeholder="<?= $tr('common.comments.reply_placeholder') ?>" maxlength="500" autocomplete="off">
                <button type="submit" class="comment-reply-form__submit" disabled aria-label="<?= $tr('common.comments.reply_send') ?>">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
                <p class="form-field__error" data-reply-error role="alert" hidden
                   style="color: red; margin-top: 8px; font-size: 14px; width: 100%;"></p>
              </form>

              <!-- Ответы скрыты до нажатия «View N more replies» -->
              <div class="comment-replies" data-replies hidden></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Шаблон карточки комментария (клонируется book.js: ответ сервера + загрузка из БД) -->
  <template id="comment-card-template">
    <div class="comment-card">
      <div class="comment-card__inner">
        <div class="avatar avatar--sm"></div>
        <div class="comment-card__content">
          <!-- Текст слева, «три точки» справа (на месте бывшего сердечка) -->
          <div class="comment-card__main">
            <div class="comment-card__head">
              <div class="comment-card__author" data-c-author></div>
              <div class="comment-card__text" data-c-text></div>
            </div>

            <!-- Меню «три точки»: удаление (только автор) + «Пожаловаться»
                 (всем). Видимость пунктов гонит comments.js -->
            <?php $view->include('comment-menu', ['variant' => 'comment']); ?>
          </div>

          <!-- Под текстом: сердечко + Reply слева, дата справа (под «тремя точками») -->
          <div class="comment-card__footer">
            <div class="comment-card__meta">
              <!-- Лайк комментария: общий обработчик card-feed.js (POST/DELETE /api/posts/{id}/like) -->
              <button type="button" class="btn-icon-small btn-like comment-card__like"
                      data-like-btn
                      data-like-id=""
                      data-liked="0"
                      aria-pressed="false" aria-label="<?= $tr('common.comments.like') ?>">
                <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span data-like-count>0</span>
              </button>
              <button type="button" class="comment-card__reply" data-reply-toggle
                      aria-expanded="false" aria-label="<?= $tr('common.comments.reply') ?>"><?= $tr('common.comments.reply') ?></button>
            </div>
            <span class="comment-card__date" data-c-date></span>
          </div>

          <!-- Ещё ниже: «View N more replies» (book.js показывает при ответах) -->
          <button type="button" class="comment-card__more" data-replies-toggle hidden></button>

          <form class="comment-reply-form" data-reply-form hidden>
            <input type="text" class="comment-reply-form__input" placeholder="<?= $tr('common.comments.reply_placeholder') ?>" maxlength="500" autocomplete="off">
            <button type="submit" class="comment-reply-form__submit" disabled aria-label="<?= $tr('common.comments.reply_send') ?>">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <p class="form-field__error" data-reply-error role="alert" hidden
               style="color: red; margin-top: 8px; font-size: 14px; width: 100%;"></p>
          </form>

          <!-- Ответы: скрыты до нажатия «View N more replies» -->
          <div class="comment-replies" data-replies hidden></div>
        </div>
      </div>
    </div>
  </template>

  <template id="reply-template">
    <div class="comment-reply">
      <?php $view->include('avatar', [
          'size'     => 'sm',
          'initials' => $meInitials,
          'icon'     => $meAvatar,
          'src'      => is_string($meAvatar) && str_contains($meAvatar, '/') ? $meAvatar : null,
      ]); ?>
      <div class="comment-reply__content">
        <!-- Текст слева, «три точки» справа (на месте бывшего сердечка) -->
        <div class="comment-reply__row">
          <div class="comment-reply__body">
            <div class="comment-reply__author"></div>
            <div class="comment-reply__text"></div>
          </div>
          <!-- Меню ответа «три точки»: удаление (только автор) + «Пожаловаться»
               (всем). Видимость пунктов гонит comments.js -->
          <?php $view->include('comment-menu', ['variant' => 'reply']); ?>
        </div>
        <!-- Под текстом: сердечко + Reply слева, дата справа (под «тремя точками») -->
        <div class="comment-card__footer comment-reply__footer">
          <div class="comment-card__meta">
            <!-- Лайк ответа — как у комментария -->
            <button type="button" class="btn-icon-small btn-like comment-reply__like"
                    data-like-btn
                    data-like-id=""
                    data-liked="0"
                    aria-pressed="false" aria-label="<?= $tr('common.comments.like') ?>">
              <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span data-like-count>0</span>
            </button>
            <!-- Ответ на ответ идёт в тот же плоский список (без вложенности) -->
            <button type="button" class="comment-card__reply" data-reply-toggle
                    aria-expanded="false" aria-label="<?= $tr('common.comments.reply') ?>"><?= $tr('common.comments.reply') ?></button>
          </div>
          <span class="comment-card__date" data-reply-date></span>
        </div>
      </div>
    </div>
  </template>

  <?php if ($isDraft): ?>
    <!-- ============================================================
         КНОПКА ПУБЛИКАЦИИ — внизу страницы, видна ВСЕГДА, пока у статьи
         статус draft (по образцу book-details). Кликом открывается
         article-rights-dialog (article-publish.js): выбор редакторов и
         POST /api/articles/{id}/publish → перезагрузка.
         Кнопка рендерится скрытой (hidden), показывает permissions.js
         по data-requires="publication.publish" (+ owner-override из
         data-requires-owner).
         ============================================================ -->
    <section class="publish-bar" data-publish-bar>
      <div class="publish-bar__text">
        <strong class="publish-bar__label"><?= $tr('common.article.draft_badge') ?></strong>
        <span class="publish-bar__hint"><?= $tr('common.article.publish_bar_text') ?></span>
      </div>
      <button type="button"
        class="btn btn--primary publish-bar__cta"
        data-article-publish-open
        data-requires="publication.publish"
        data-requires-kind="article"
        data-requires-owner="<?= $isOwnerPub ? '1' : '0' ?>"
        hidden
        data-article-id="<?= (int) $articleId ?>">
        <?= $tr('common.article.publish_cta') ?>
      </button>
    </section>
  <?php endif; ?>

  <!-- Окно выбора прав при публикации статьи (article-publish.js) -->
  <?php $view->include('article-rights-dialog'); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <!-- details-menu.js — меню «три точки» в шапке: открытие/закрытие +
       удаление статьи (DELETE /api/articles/{id}, контракт — в partial) -->
  <script src="/assets/js/details-menu.js"></script>
  <script src="/assets/js/publication-roles.js"></script>
  <!-- article-publish.js — окно выбора прав при публикации статьи
       (кнопка publish-bar у черновика, data-article-publish-open) -->
  <script src="/assets/js/article-publish.js"></script>
  <!-- roles-module.js — модуль «назначение ролей» (управляет окном
       публикации: window.LoreRoles; подключается после article-publish.js) -->
  <script src="/assets/js/roles-module.js" defer></script>
  <!-- comments.js — общая структура комментариев (карточка, ответы, кнопки
       Show more/less); book.js подключается после неё -->
  <script src="/assets/js/comments.js"></script>
  <script src="/assets/js/book.js"></script>
<?php $view->endBlock('scripts'); ?>