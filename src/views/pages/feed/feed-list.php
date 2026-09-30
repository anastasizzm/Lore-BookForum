<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'for-you'); ?>

<?php $view->startBlock('title'); ?>For you — Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('content'); ?>

<?php
// DEV: тестовые данные для предпросмотра, открывать с &preview=1
if (isset($_GET['preview'])) {
    $items = [
        (object) [
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

          $view->include('card-feed', [
              'withBook'     => $publication !== null,
              'bookCover'    => $publication?->cover     ?? '',
              'bookTitle'    => $publication?->title     ?? '',
              'bookAuthor'   => $publication?->author    ?? '',
              'userInitials' => strtoupper(substr($creator?->name ?? '', 0, 1) . substr($creator?->surname ?? '', 0, 1)),
              'userName'     => $creator?->username      ?? '',
              'userAvatar'   => $creator?->avatar        ?? null,
              'text'         => $post->content,
              'likes'        => 0,  // TODO: пока нет в DTO
              'comments'     => 0,  // TODO: пока нет в DTO
              'date'         => $post->createdAt->format('d.m.Y'),
          ]);
        ?>
      <?php endforeach; ?>
    </div>

    <?php if (($meta['hasNext'] ?? false)): ?>
      <div class="feed-panel__load-more">
        <a href="?page=<?= ($meta['page'] ?? 1) + 1 ?>" class="btn btn--secondary">Load more</a>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php $view->endBlock('content'); ?>