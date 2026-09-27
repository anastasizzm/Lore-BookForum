<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'for-you'); ?>

<?php $view->startBlock('title'); ?>For you — Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('content'); ?>

<?php
// Поиск — в page-header как action
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

<?php
// TODO: заменить на данные из контроллера ($posts)
// Пока — заглушки для проверки вёрстки

$posts = $posts ?? [
    [
        'userInitials' => 'UN',
        'userName'     => 'username',
        'userAvatar'   => null,
        'date'         => '10.09.2026',
        'text'         => 'some text about life and many more things some text about life and many more things some text about life and many more things.',
        'likes'        => 0,
        'comments'     => 0,
        'bookCover'    => 'https://placehold.co/80x112?text=Book',
        'bookTitle'    => 'Name of book',
        'bookAuthor'   => 'Author',
    ],
    [
        'userInitials' => 'ST',
        'userName'     => 'sername',
        'userAvatar'   => null,
        'date'         => '10.09.2026',
        'text'         => 'some text about life and many more things some text about life and many more things some text about life and many more things.',
        'likes'        => 0,
        'comments'     => 0,
        'bookCover'    => 'https://placehold.co/80x112?text=Book',
        'bookTitle'    => 'Name of book',
        'bookAuthor'   => 'Author',
    ],
    [
        'userInitials' => 'ST',
        'userName'     => 'sername',
        'userAvatar'   => null,
        'date'         => '10.09.2026',
        'text'         => 'some text about life and many more things some text about life and many more things some text about life and many more things.',
        'likes'        => 0,
        'comments'     => 0,
        'bookCover'    => 'https://placehold.co/80x112?text=Book',
        'bookTitle'    => 'Name of book',
        'bookAuthor'   => 'Author',
    ],
];
?>

<?php if (empty($posts)): ?>

  <div class="empty-state">
    <p class="empty-state__text">No posts yet. Be the first to share your thoughts.</p>
  </div>

<?php else: ?>

  <div class="feed-panel">
    <div class="stack">
      <?php foreach ($posts as $post): ?>
        <?php $view->include('card-feed', [
            'withBook'     => true,
            'bookCover'    => $post['bookCover']    ?? '',
            'bookTitle'    => $post['bookTitle']    ?? '',
            'bookAuthor'   => $post['bookAuthor']   ?? '',
            'userInitials' => $post['userInitials'] ?? '',
            'userName'     => $post['userName']     ?? '',
            'userAvatar'   => $post['userAvatar']   ?? null,
            'text'         => $post['text']         ?? '',
            'likes'        => $post['likes']        ?? 0,
            'comments'     => $post['comments']     ?? 0,
            'date'         => $post['date']         ?? '',
        ]); ?>
      <?php endforeach; ?>
    </div>
  </div>

<?php endif; ?>

<?php $view->endBlock('content'); ?>