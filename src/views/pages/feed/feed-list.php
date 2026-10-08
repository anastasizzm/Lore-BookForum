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

<!-- Шаблон комментария под постом (клонируется из card-feed.js).
     __INITIALS__/__EMOJI__/__SRC__ — плейсхолдеры: JS оставляет один из
     трёх вариантов аватара (пресет-эмодзи / картинка / инициалы) -->
<template id="feed-comment-template">
  <div class="feed-comment">
    <div class="feed-comment__avatar">
      <div data-fc-avatar-initials hidden><?php $view->include('avatar', ['size' => 'sm', 'initials' => '__INITIALS__', 'src' => null]); ?></div>
      <div data-fc-avatar-emoji hidden><div class="avatar avatar--sm"><span class="avatar__emoji" aria-hidden="true">__EMOJI__</span></div></div>
      <div data-fc-avatar-img hidden><?php $view->include('avatar', ['size' => 'sm', 'initials' => '__INITIALS__', 'src' => '__SRC__']); ?></div>
    </div>
    <div class="feed-comment__body">
      <div class="feed-comment__head">
        <span class="feed-comment__author" data-fc-author></span>
        <span class="feed-comment__date" data-fc-date></span>
      </div>
      <div class="feed-comment__text" data-fc-text></div>

      <div class="feed-comment__actions">
        <button type="button" class="action-btn action-like" data-like-btn data-liked="0" aria-pressed="false" aria-label="Like">
          <svg width="16" height="14" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span data-like-count>0</span>
        </button>
        <button type="button" class="action-btn action-comment" data-fc-reply aria-expanded="false">Reply</button>
      </div>

      <form class="comment-form comment-form--reply" data-fc-reply-form hidden novalidate>
        <input type="text" class="comment-form__input" placeholder="Write a reply…" maxlength="500" autocomplete="off">
        <button type="submit" class="comment-form__submit" disabled>Reply</button>
        <p data-comment-error role="alert" hidden></p>
        <p data-comment-status role="status" hidden></p>
      </form>
    </div>
  </div>
</template>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
  <!-- Поиск в ленте по названию книги (не по автору): filters.js
       делегирует сюда сабмит поля ?q= — см. feed-search.js -->
  <script src="<?= $view->asset('js/feed-search.js') ?>"></script>
<?php $view->endBlock('scripts'); ?>