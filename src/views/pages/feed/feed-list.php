<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'for-you'); ?>

<?php $view->startBlock('title'); ?>
For you — Book App
<?php $view->endBlock('title'); ?>

<?php $view->startBlock('content'); ?>

<?php
// Собираем HTML поиска и передаём его в page-header как параметр
ob_start();
$view->include('input', [
    'type'        => 'search',
    'name'        => 'q',
    'placeholder' => 'Search',
    'value'       => '',
]);
$searchHtml = ob_get_clean();

$view->include('page-header', [
    'title'   => 'For you',
    'actions' => $searchHtml,
]);
?>

<?php
// Заглушки — потом заменим на данные из контроллера ($posts придёт из бэка)
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

<div class="feed-panel">
  <div class="stack">
    <?php foreach ($posts as $post): ?>
      <?php $view->include('card-feed', [
          'withBook'     => true,
          'bookCover'    => $post['bookCover'],
          'bookTitle'    => $post['bookTitle'],
          'bookAuthor'   => $post['bookAuthor'],
          'userInitials' => $post['userInitials'],
          'userName'     => $post['userName'],
          'userAvatar'   => $post['userAvatar'],
          'text'         => $post['text'],
          'likes'        => $post['likes'],
          'comments'     => $post['comments'],
          'date'         => $post['date'],
      ]); ?>
    <?php endforeach; ?>
  </div>
</div>

<?php $view->endBlock('content'); ?>