<?php
/**
 * Шаблон ленты «For you».
 * Только разметка карточек. Подключается из public/index.php.
 * Здесь же позже будут реальные данные из БД.
 */

// Временный массив заглушек — потом заменим на данные из БД
$posts = [
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
      <?php
        // Раскладываем массив в переменные, которые ждёт card-feed.php
        $userInitials = $post['userInitials'];
        $userName     = $post['userName'];
        $userAvatar   = $post['userAvatar'];
        $date         = $post['date'];
        $text         = $post['text'];
        $likes        = $post['likes'];
        $comments     = $post['comments'];
        $bookCover    = $post['bookCover'];
        $bookTitle    = $post['bookTitle'];
        $bookAuthor   = $post['bookAuthor'];
        $withBook     = true;

        include __DIR__ . '/../../../components/card-feed.php';
      ?>
    <?php endforeach; ?>
  </div>
</div>