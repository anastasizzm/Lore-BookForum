<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'for-you'); ?>

<?php $view->startBlock('title'); ?>For you — Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('content'); ?>

<?php
// DEV: тестовые данные для предпросмотра, открывать с &preview=1
if (isset($_GET['preview'])) {
    $items = [
        (object) [
            'id' => 1,
            'creator' => (object) [
                'name' => 'Иван', 'surname' => 'Иванов',
                'username' => 'ivan', 'avatar' => 'cat',
            ],
            'publication' => (object) [
                'cover' => '', 'title' => 'Тестовая книга', 'author' => 'Автор Авторов',
            ],
            'content'   => 'Пример поста с привязанной книгой, чтобы посмотреть карточку.',
            'createdAt' => new DateTimeImmutable('2026-09-30'),
        ],
        (object) [
            'id' => 2,
            'creator' => (object) [
                'name' => 'Анна', 'surname' => 'Петрова',
                'username' => 'anna', 'avatar' => 'default',
            ],
            'publication' => null, // пост без книги
            'content'   => 'Комментарий без книги. Длинный текст для проверки переноса строк: lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor.',
            'createdAt' => new DateTimeImmutable('2026-09-29'),
        ],
    ];
    $meta = ['hasNext' => false];
}
?>

<?php
// Поиск
ob_start();
$view->include('input', [
    'type'        => 'search',
    'name'        => 'q',
    'placeholder' => 'Search',
    'value'       => $searchQuery ?? '',
]);
$searchHtml = ob_get_clean();

$view->include('page-header', [
    'title'   => 'For you',
    'actions' => $searchHtml,
]);
?>

<?php if (!empty($innerMessages)): ?>
  <div class="messages">
    <?php foreach ($innerMessages as $msg): ?>
      <div class="message message--error">
        <div class="message__title"><?= $view->e($msg->title ?? '') ?></div>
        <div class="message__body"><?= $view->e($msg->body ?? '') ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
/**
 * PostsService::getListWithContext() отдаёт WithContext<Post, PostContext>
 * (item + isLiked/isEditor). Контекст нужен карточке (data-liked), сам Post —
 * циклу ниже, поэтому разбираем на пару [post, liked].
 * Preview-данные выше уже лежат в виде stdClass, они остаются как есть.
 */
$items = array_map(
    static function ($it) {
        if ($it instanceof \App\Models\UserContext\WithContext) {
            return ['post' => $it->item, 'liked' => (bool) ($it->context->isLiked ?? false)];
        }
        return ['post' => $it, 'liked' => false];
    },
    $items ?? []
);
?>

<?php if (empty($items)): ?>

  <div class="empty-state">
    <p class="empty-state__text">No posts yet. Be the first to share your thoughts.</p>
  </div>

<?php else: ?>

  <div class="feed-panel">
    <div class="stack">
      <?php foreach ($items as $entry): ?>
        <?php
          $post = $entry['post'];
          $creator     = $post->creator;
          $publication = $post->publication;

          // Инициалы из name + surname (в UserShortData это отдельные поля)
          $initials = mb_strtoupper(
              mb_substr($creator?->name    ?? '', 0, 1) .
              mb_substr($creator?->surname ?? '', 0, 1)
          );
          if ($initials === '') {
              $initials = mb_strtoupper(mb_substr($creator?->username ?? '', 0, 1));
          }

          // id публикации: у модели Post он приватный (геттер), у DEV-заглушки его нет
          $publicationId = method_exists($post, 'getPublicationId')
              ? $post->getPublicationId()
              : 0;

          // Обложка: iconId — объект (Uuid), ссылки на файлы в API нет,
          // поэтому собираем путь как на странице Saved + плейсхолдер в card-feed.php.
          $iconId = $publication?->iconId ?? null;
          $iconStr = (is_string($iconId) || (is_object($iconId) && method_exists($iconId, '__toString')))
              ? (string) $iconId : '';
          $coverUrl = $iconStr !== '' ? '/uploads/covers/' . $iconStr : '';

          // Текущий пользователь — для оптимистичной вставки своего комментария
          $cu = $user ?? null;
          $cuInitials = mb_strtoupper(
              mb_substr($cu?->name ?? '', 0, 1) . mb_substr($cu?->surname ?? '', 0, 1)
          );
          if ($cuInitials === '') {
              $cuInitials = mb_strtoupper(mb_substr($cu?->username ?? '', 0, 1));
          }

          $view->include('card-feed', [
              'postId'        => $post->id ?? 0,
              'publicationId' => $publicationId,
              'withBook'      => $publication !== null,
              'bookCover'     => $coverUrl,
              'bookTitle'     => $publication?->title  ?? '',
              'userInitials'  => $initials,
              'userName'      => $creator?->username   ?? '',
              // Сырой ключ аватара из БД ('cat', 'fox', 'default'...).
              // Как его показать (эмодзи или инициалы) решает partial avatar.php.
              'userAvatar'    => $creator?->avatar     ?? '',
              'text'          => $post->content,
              'likes'         => $post->likesCount ?? 0,
              'comments'      => $post->commentsCount ?? 0,
              'liked'         => $entry['liked'],
              'date'          => $post->createdAt->format('d.m.Y'),
              'currentUserInitials' => $cuInitials,
              'currentUserName'     => $cu?->username ?? '',
              'currentUserId'       => (int) ($cu?->id ?? 0),
              // Сырой ключ аватара текущего юзера — для своих комментариев
              'currentUserAvatar'   => $cu?->avatar ?? '',
          ]);
        ?>
      <?php endforeach; ?>
    </div>

    <?php if (($meta['hasNext'] ?? false)): ?>
      <div class="feed-panel__load-more">
        <a href="?page=<?= ($meta['page'] ?? 1) + 1 ?><?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>"
           class="btn btn--secondary">Load more</a>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<!-- Шаблоны комментария под постом — ТЕ ЖЕ, что на book/article details
     (клонирует comments.js): ник автора без @, три точки сверху справа
     (только автору), внизу сердечко + Reply слева и дата справа,
     ответы — за кнопкой «View N more replies». -->
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

          <!-- Меню удаления: раскрывается НАД кнопкой; только для автора
               (comments.js скрывает врап, если authorId != мой id) -->
          <div class="comment-card__actions" data-c-menu-wrap hidden>
            <button type="button" class="btn-icon-small comment-card__menu-btn" data-c-menu-toggle
                    aria-haspopup="true" aria-expanded="false" aria-label="Comment options">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <circle cx="8" cy="3" r="1.5"/>
                <circle cx="8" cy="8" r="1.5"/>
                <circle cx="8" cy="13" r="1.5"/>
              </svg>
            </button>
            <div class="comment-card__menu" data-c-menu hidden>
              <button type="button" class="comment-card__menu-item comment-card__menu-item--danger"
                      data-c-delete>Delete</button>
            </div>
          </div>
        </div>

        <!-- Под текстом: сердечко + Reply слева, дата справа
             (на одном уровне, под «тремя точками») -->
        <div class="comment-card__footer">
          <div class="comment-card__meta">
            <!-- Лайк комментария: общий обработчик card-feed.js (POST/DELETE /api/posts/{id}/like) -->
            <button type="button" class="btn-icon-small btn-like comment-card__like"
                    data-like-btn
                    data-like-id=""
                    data-liked="0"
                    aria-pressed="false" aria-label="Like">
              <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span data-like-count>0</span>
            </button>
            <button type="button" class="comment-card__reply" data-reply-toggle
                    aria-expanded="false" aria-label="Reply">Reply</button>
          </div>
          <span class="comment-card__date" data-c-date></span>
        </div>

        <!-- Ещё ниже: «View N more replies» / «Show less» (comments.js) -->
        <button type="button" class="comment-card__more" data-replies-toggle hidden></button>

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

        <!-- Ответы: скрыты до нажатия «View N more replies» -->
        <div class="comment-replies" data-replies hidden></div>
      </div>
    </div>
  </div>
</template>

<template id="reply-template">
  <div class="comment-reply">
    <div class="avatar avatar--sm"></div>
    <div class="comment-reply__content">
      <!-- Текст слева, «три точки» справа (на месте бывшего сердечка) -->
      <div class="comment-reply__row">
        <div class="comment-reply__body">
          <div class="comment-reply__author"></div>
          <div class="comment-reply__text"></div>
        </div>
        <!-- Меню удаления: раскрывается НАД кнопкой; только для автора
             (comments.js скрывает врап, если authorId != мой id) -->
        <div class="comment-card__actions comment-reply__actions" data-c-menu-wrap hidden>
          <button type="button" class="btn-icon-small comment-card__menu-btn" data-c-menu-toggle
                  aria-haspopup="true" aria-expanded="false" aria-label="Reply options">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <circle cx="8" cy="3" r="1.5"/>
              <circle cx="8" cy="8" r="1.5"/>
              <circle cx="8" cy="13" r="1.5"/>
            </svg>
          </button>
          <div class="comment-card__menu" data-c-menu hidden>
            <button type="button" class="comment-card__menu-item comment-card__menu-item--danger"
                    data-c-delete>Delete</button>
          </div>
        </div>
      </div>
      <!-- Под текстом: сердечко + Reply слева, дата справа (под «тремя точками») -->
      <div class="comment-card__footer comment-reply__footer">
        <div class="comment-card__meta">
          <!-- Лайк ответа — как у комментария -->
          <button type="button" class="btn-icon-small btn-like comment-reply__like"
                  data-like-btn
                  data-like-id=""
                  data-liked="0"
                  aria-pressed="false" aria-label="Like">
            <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span data-like-count>0</span>
          </button>
          <!-- Ответ на ответ идёт в тот же плоский список (без вложенности) -->
          <button type="button" class="comment-card__reply" data-reply-toggle
                  aria-expanded="false" aria-label="Reply">Reply</button>
        </div>
        <span class="comment-card__date" data-reply-date></span>
      </div>
    </div>
  </div>
</template>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <!-- comments.js — общая структура комментариев (карточка, ответы, кнопки
       Show more/less); card-feed.js (defer) выполнится после неё -->
  <script src="<?= $view->asset('js/comments.js') ?>"></script>
  <!-- Поиск в ленте по названию книги (не по автору): filters.js
       делегирует сюда сабмит поля ?q= — см. feed-search.js -->
  <script src="<?= $view->asset('js/feed-search.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>