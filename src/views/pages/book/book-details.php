<?php
/**
 * Ожидаемые переменные от контроллера:
 *   $book — объект App\Models\Publications\Book
 *           (id, title, createdAt, iconId, creator, genre + опционально: rating, savesCount,
 *            isbn, annotation, authorNote, series, category, tableOfContents, isSaved, readingStatus)
 *   $comments — массив постов (те же поля, что в feed-list.php);
 *               контроллер его НЕ передаёт — book.js подгружает комментарии
 *               сам из GET /api/posts?publication={id}&include=creator (P0-5)
 *   $user     — App\Models\Users\UserContext текущего пользователя
 *               (имя/инициалы/аватар в форме комментария и в шаблоне ответа)
 *
 * Поля, которых может не быть в модели, читаются через ?? — чтобы страница не падала.
 * Объекты (genre, category, series) приводятся к строке через хелпер $str — чтобы
 * не ловить "Object of class BasicModel could not be converted to string".
 */

/**
 * Контроллер отдаёт 'wrapper' => WithContext<Book, PublicationContext>
 * (BooksController::retrieve): item — сама книга, context — данные текущего
 * юзера (isSaved / isEditor / readingStatus). Старое имя 'book' поддерживаем.
 */
$wrapper   = $wrapper ?? null;
$book      = $book ?? null;
$readerCtx = null;

if ($book === null && $wrapper !== null) {
    if ($wrapper instanceof \App\Models\UserContext\WithContext) {
        $book      = $wrapper->item;
        $readerCtx = $wrapper->context;
    } else {
        $book = $wrapper;
    }
}
if ($book === null) {
    return;
}

/* ---------- Универсальное приведение к строке ---------- */
$str = static function ($v): string {
    if ($v === null) return '';
    if (is_string($v)) return $v;
    if (is_scalar($v)) return (string) $v;
    if (is_object($v)) {
        if (method_exists($v, '__toString')) return (string) $v;
        if (isset($v->title)) return (string) $v->title;
        if (isset($v->name))  return (string) $v->name;
    }
    return '';
};

/* ---------- Скалярные значения ---------- */

$publicationId = (int)    ($book->id ?? 0);
$bookId        = (int)    ($book->id ?? 0);
// isSaved / readingStatus живут в контексте юзера (PublicationContext), а не в модели
$readingMap  = ['none' => 'new', 'reading' => 'in_progress', 'ended' => 'finished'];
$statusRaw   = $readerCtx?->readingStatus?->value ?? $book->readingStatus ?? 'new';
$isSaved       = (bool)   ($readerCtx?->isSaved ?? $book->isSaved ?? false);
$readingStatus = (string) ($readingMap[$statusRaw] ?? $statusRaw);
$savesCount    = (int)    ($book->savedCount    ?? 0);

// rating_avg в БД хранится умноженным на 10 (46 -> 4.6)
$rating = (float) ($book->rating ?? 0);
if ($rating > 5) {
    $rating /= 10;
}

$percent = number_format(max(0, min(100, $rating / 5 * 100)), 2, '.', '');

/* ---------- Даты ---------- */

$createdAt = '';
if (($book->createdAt ?? null) instanceof \DateTimeInterface) {
    $createdAt = $book->createdAt->format('d.m.Y');
} elseif (is_string($book->createdAt ?? null)) {
    $createdAt = $book->createdAt;
}

/* ---------- Автор ---------- */

$authorName = '';
if (($book->creator ?? null) !== null) {
    $authorName = trim(($book->creator->name ?? '') . ' ' . ($book->creator->surname ?? ''));
    if ($authorName === '') {
        $authorName = (string) ($book->creator->username ?? '');
    }
}

/* ---------- Жанр / категория / серия / ISBN ---------- */

$genreTitle     = $str($book->genre     ?? null);
$categoryTitle  = $str($book->category  ?? null);
$seriesTitle    = $str($book->series    ?? null);
$publisherTitle = $str($book->publisher ?? null);
$isbn           = $str($book->isbn      ?? null);

/* ---------- Аннотация / заметка автора / TOC ---------- */
// В модели поля называются description / authorNotes (не annotation / authorNote).

$annotation      = (string) ($book->description   ?? '');
$authorNote      = (string) ($book->authorNotes   ?? '');
$tableOfContents = (array)  ($book->tableOfContents ?? []);

/* ---------- Обложка ---------- */

$coverUrl = 'https://placehold.co/400x560?text=Cover';
if (!empty($book->iconId)) {
    if ($book->iconId instanceof \Stringable) {
        $coverUrl = '/uploads/covers/' . (string) $book->iconId;
    } elseif (is_string($book->iconId)) {
        $coverUrl = '/uploads/covers/' . $book->iconId;
    }
}

/* ---------- Статус чтения -> кнопка ---------- */

$readingButtons = [
    'new'         => ['label' => 'Start reading',  'modifier' => 'start'],
    'in_progress' => ['label' => 'Resume reading', 'modifier' => 'resume'],
    'finished'    => ['label' => 'Read again',     'modifier' => 'again'],
];
if (!isset($readingButtons[$readingStatus])) {
    $readingStatus = 'new';
}
$readingBtn = $readingButtons[$readingStatus];

/* ---------- Комментарии ---------- */
/**
 * Список комментариев контроллер не передаёт (P0-5): book.js подгружает их
 * сам из GET /api/posts?publication={id}&include=creator. Здесь нужен только
 * счётчик (реальное число из БД) и признак пустого списка.
 */
$comments      = $comments ?? [];
$totalComments = (int) ($totalComments ?? ($book->commentsCount ?? count($comments)));

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
?>
<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'library'); ?>

<?php $view->startBlock('title'); ?>Book details<?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="/assets/css/book.css">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

  <div class="book-page-header">
    <h1 class="book-page-title">Book details</h1>
  </div>

  <div class="book-details">
    <div class="book-details__cover-col">
      <div class="book-details__cover">
        <img src="<?= $view->e($coverUrl) ?>"
             alt="<?= $view->e($book->title ?? '') ?> cover"
             onerror="this.onerror = null; this.src = '/img/book-placeholder.svg';">
      </div>

      <div class="book-actions">
        <button type="button"
                class="btn-icon btn-icon--circle<?= $isSaved ? ' is-active' : '' ?>"
                data-save-book
                data-book-id="<?= $bookId ?>"
                data-save-url="/api/books/<?= $bookId ?>/save"
                aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                aria-label="<?= $isSaved ? 'Remove from saved' : 'Save book' ?>">
          <svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </button>
        <button type="button"
                class="btn btn--primary btn--pill btn--read btn--read-<?= $view->e($readingBtn['modifier']) ?>"
                data-start-reading
                data-reading-status="<?= $view->e($readingStatus) ?>"><?= $view->e($readingBtn['label']) ?></button>
      </div>

      <div class="book-rating">
        <div class="book-rating__group">
          <span class="book-rating__stars"
                style="--rating-percent: <?= $view->e($percent) ?>%;"
                role="img"
                aria-label="Rating <?= $view->e(number_format($rating, 1)) ?> out of 5">
            ★★★★★
          </span>
          <span class="book-rating__value"><?= $view->e(number_format($rating, 1)) ?></span>
        </div>
        <span class="book-rating__saves">
          <svg width="12" height="16" viewBox="0 0 12 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 2C1 1.44772 1.44772 1 2 1H10C10.5523 1 11 1.44772 11 2V14.5273C11 14.928 10.5574 15.1704 10.2039 14.9631L6 12.5L1.79612 14.9631C1.44265 15.1704 1 14.928 1 14.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
          </svg>
          <span data-saves-count><?= $savesCount ?></span>
        </span>
      </div>

      <div class="rate" data-rate role="radiogroup" aria-label="Rate this book">
        <span class="rate__label">Click to Rate:</span>
        <div class="rate__stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button"
                    class="rate__star"
                    data-rate-value="<?= $i ?>"
                    role="radio"
                    aria-checked="false"
                    aria-label="<?= $i ?> out of 5">
              <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2.5l2.94 5.96 6.56.95-4.75 4.63 1.12 6.54L12 17.5l-5.87 3.08 1.12-6.54L2.5 9.41l6.56-.95L12 2.5z"/>
              </svg>
            </button>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="card-base info-box">
      <h2 class="info-box__title"><?= $view->e($book->title ?? '') ?></h2>
      <p class="info-box__meta"><?= $view->e($authorName) ?></p>
      <p class="info-box__meta">Creation date: <?= $view->e($createdAt) ?></p>
      <?php if ($genreTitle !== ''): ?>
        <p class="info-box__meta">Genre: <?= $view->e($genreTitle) ?></p>
      <?php endif; ?>
      <?php if ($categoryTitle !== ''): ?>
        <p class="info-box__meta">Category: <?= $view->e($categoryTitle) ?></p>
      <?php endif; ?>
      <?php if ($seriesTitle !== ''): ?>
        <p class="info-box__meta">Book series: <?= $view->e($seriesTitle) ?></p>
      <?php endif; ?>
      <?php if ($publisherTitle !== ''): ?>
        <p class="info-box__meta">Publisher: <?= $view->e($publisherTitle) ?></p>
      <?php endif; ?>
      <?php if ($isbn !== ''): ?>
        <p class="info-box__meta">ISBN: <?= $view->e($isbn) ?></p>
      <?php endif; ?>

      <div class="book-tabs-panel">
        <?php
          $view->include('tabs', [
              'variant' => 'outline',
              'items'   => [
                  ['label' => 'Annotation', 'href' => '#annotation', 'active' => true, 'row' => 'annotation'],
                  ['label' => 'Table of contents', 'href' => '#toc', 'active' => false, 'row' => 'toc'],
              ],
          ]);
        ?>

        <div class="card-base book-tabs-panel__content" data-row="annotation">
          <?php if ($annotation !== ''): ?>
            <p class="info-box__body"><?= nl2br($view->e($annotation)) ?></p>
          <?php else: ?>
            <p class="info-box__body">Annotation is not available yet.</p>
          <?php endif; ?>

          <?php if ($authorNote !== ''): ?>
            <div class="book-tabs-panel__note">
              <strong>Author's Note:</strong><br>
              <?= nl2br($view->e($authorNote)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card-base book-tabs-panel__content" data-row="toc" hidden>
          <?php if (empty($tableOfContents)): ?>
            <p class="info-box__body">Table of contents is not available yet.</p>
          <?php else: ?>
            <ol class="info-box__body">
              <?php foreach ($tableOfContents as $chapter): ?>
                <li><?= $view->e($chapter) ?></li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Блок комментариев.
       data-me-username = логин, как его отдаёт API (creator.username): оптимистично
       вставленный комментарий/ответ должен совпадать с тем, что покажет перезагрузка. -->
  <section class="comments-section" data-comments
           data-publication-id="<?= (int) $publicationId ?>"
           data-me-id="<?= (int) ($me->id ?? 0) ?>"
           data-me-name="<?= $view->e($meName) ?>"
           data-me-username="<?= $view->e((string) ($me->username ?? '')) ?>"
           data-me-initials="<?= $view->e($meInitials) ?>"
           data-me-avatar="<?= $view->e($meAvatar ?? '') ?>">
    <h2 class="comments-section__title">
      Comments: <span data-comments-count><?= (int) $totalComments ?></span>
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
        <?php $view->include('avatar', ['size' => 'sm', 'initials' => $meInitials, 'avatar' => $meAvatar]); ?>
        <div class="comment-card__content">
          <div class="comment-card__author"><?= $view->e($meName) ?></div>
          <div class="comment-card__row">
            <input class="comment-card__input" type="text" name="content"
                   maxlength="2000" autocomplete="off"
                   placeholder="Input comments...">
            <!-- Кнопка отправки (галочка): без неё Enter не выглядел «отправкой» (P0-2) -->
            <button type="submit" class="comment-card__send" data-comment-send disabled aria-label="Send comment">
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
      Be the first to comment.
    </p>

    <!-- Список комментариев: сервер его не передаёт, book.js наполняет из API (P0-5) -->
    <div class="stack" data-comment-list>
      <?php foreach ($comments as $comment): ?>
        <?php
          $cId    = (int) ($comment['id'] ?? 0);
          $cLikes = (int) ($comment['likes'] ?? 0);
          $cLiked = (bool) ($comment['liked'] ?? false);
        ?>
        <div class="comment-card"<?= $cId ? ' data-comment-id="' . $cId . '"' : '' ?>>
          <div class="comment-card__inner">
            <?php $view->include('avatar', ['size' => 'sm', 'initials' => $comment['userInitials'] ?? 'SN', 'avatar' => $comment['userAvatar'] ?? '']); ?>

            <div class="comment-card__content">
              <div class="comment-card__author"><?= $view->e($comment['userName']) ?></div>
              <div class="comment-card__text"><?= nl2br($view->e($comment['text'])) ?></div>

              <div class="comment-card__footer">
                <!-- Лайк комментария: общий обработчик card-feed.js (POST/DELETE /api/posts/{id}/like) (P0-4) -->
                <button type="button" class="btn-icon-small btn-like<?= $cLiked ? ' is-liked' : '' ?>"
                        data-like-btn
                        data-like-id="<?= $cId ?>"
                        data-liked="<?= $cLiked ? '1' : '0' ?>"
                        aria-pressed="<?= $cLiked ? 'true' : 'false' ?>" aria-label="Like">
                  <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <span data-like-count><?= $cLikes ?></span>
                </button>

                <div class="comment-card__meta">
                  <span><?= $view->e($comment['date']) ?></span>
                  <button type="button" class="btn-icon-small" data-reply-toggle aria-expanded="false" aria-label="Reply">
                    <svg width="16" height="13" viewBox="0 0 16 13" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M5.5 1L1 5.5M1 5.5L5.5 10M1 5.5H11.5C13.9853 5.5 16 7.51472 16 10V12.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                </div>
              </div>

              <form class="comment-reply-form" data-reply-form hidden>
                <input type="text" class="comment-reply-form__input" placeholder="Write a reply…" maxlength="500" autocomplete="off">
                <button type="submit" class="comment-reply-form__submit" disabled aria-label="Send reply">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
                <p class="form-field__error" data-reply-error role="alert" hidden
                   style="color: red; margin-top: 8px; font-size: 14px; width: 100%;"></p>
              </form>

              <div class="comment-replies" data-replies></div>
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
          <div class="comment-card__author" data-c-author></div>
          <div class="comment-card__text" data-c-text></div>

          <div class="comment-card__footer">
            <button type="button" class="btn-icon-small btn-like"
                    data-like-btn
                    data-like-id=""
                    data-liked="0"
                    aria-pressed="false" aria-label="Like">
              <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span data-like-count>0</span>
            </button>

            <div class="comment-card__meta">
              <span data-c-date></span>
              <button type="button" class="btn-icon-small" data-reply-toggle aria-expanded="false" aria-label="Reply">
                <svg width="16" height="13" viewBox="0 0 16 13" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M5.5 1L1 5.5M1 5.5L5.5 10M1 5.5H11.5C13.9853 5.5 16 7.51472 16 10V12.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>

          <form class="comment-reply-form" data-reply-form hidden>
            <input type="text" class="comment-reply-form__input" placeholder="Write a reply…" maxlength="500" autocomplete="off">
            <button type="submit" class="comment-reply-form__submit" disabled aria-label="Send reply">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <p class="form-field__error" data-reply-error role="alert" hidden
               style="color: red; margin-top: 8px; font-size: 14px; width: 100%;"></p>
          </form>

          <div class="comment-replies" data-replies></div>
        </div>
      </div>
    </div>
  </template>

  <template id="reply-template">
    <div class="comment-reply">
      <?php $view->include('avatar', ['size' => 'sm', 'initials' => $meInitials, 'avatar' => $meAvatar]); ?>
      <div class="comment-reply__content">
        <div class="comment-reply__author"><?= $view->e($meName) ?></div>
        <div class="comment-reply__text"></div>
      </div>
    </div>
  </template>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <script src="/assets/js/book.js"></script>
<?php $view->endBlock('scripts'); ?>