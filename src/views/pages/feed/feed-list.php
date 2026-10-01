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
                'username' => 'ivan', 'avatar' => null,
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
                'username' => 'anna', 'avatar' => null,
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

<?php if (empty($items)): ?>

  <div class="empty-state">
    <p class="empty-state__text">No posts yet. Be the first to share your thoughts.</p>
  </div>

<?php else: ?>

  <div class="feed-panel">
    <div class="stack">
      <?php foreach ($items as $post): ?>
        <?php
          $creator     = $post->creator;
          $publication = $post->publication;

          // Инициалы из name + surname (в UserShortData это отдельные поля)
          $initials = mb_strtoupper(
              mb_substr($creator?->name    ?? '', 0, 1) .
              mb_substr($creator?->surname ?? '', 0, 1)
          );

          // Аватар: 'default' в БД означает «нет аватара» → передаём null,
          // чтобы avatar.php отрендерил инициалы вместо битой картинки.
          // Если у тебя аватары лежат в другой папке — поменяй '/uploads/avatars/'.
          $avatarRaw = $creator?->avatar ?? '';
          $avatarSrc = ($avatarRaw !== '' && $avatarRaw !== 'default')
              ? '/uploads/avatars/' . $avatarRaw
              : null;

          // id публикации: у модели Post он приватный (геттер), у DEV-заглушки его нет
          $publicationId = method_exists($post, 'getPublicationId')
              ? $post->getPublicationId()
              : 0;

          $view->include('card-feed', [
              'postId'        => $post->id ?? 0,
              'publicationId' => $publicationId,
              'withBook'      => $publication !== null,
              'bookCover'     => $publication?->iconId ?? '',
              'bookTitle'     => $publication?->title  ?? '',
              'userInitials'  => $initials,
              'userName'      => $creator?->username   ?? '',
              'userAvatar'    => $avatarSrc,
              'text'          => $post->content,
              'likes'         => $post->likesCount ?? 0,
              'comments'      => $post->commentsCount ?? 0,
              'date'          => $post->createdAt->format('d.m.Y'),
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
     __INITIALS__ и __SRC__ — плейсхолдеры: JS оставляет один из двух вариантов аватара -->
<template id="feed-comment-template">
  <div class="feed-comment">
    <div class="feed-comment__avatar">
      <div data-fc-avatar-initials hidden><?php $view->include('avatar', ['size' => 'sm', 'initials' => '__INITIALS__', 'src' => null]); ?></div>
      <div data-fc-avatar-img hidden><?php $view->include('avatar', ['size' => 'sm', 'initials' => '', 'src' => '__SRC__']); ?></div>
    </div>
    <div class="feed-comment__body">
      <div class="feed-comment__head">
        <span class="feed-comment__author" data-fc-author></span>
        <span class="feed-comment__date" data-fc-date></span>
      </div>
      <div class="feed-comment__text" data-fc-text></div>
    </div>
  </div>
</template>

<?php $view->endBlock('content'); ?>