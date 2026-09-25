<?php
$active = 'for-you';
$pageTitle = 'For you';

// Собираем поиск
ob_start();
$type = 'search';
$name = 'q';
$placeholder = 'Search';
$value = '';
include __DIR__ . '/../src/components/input.php';
$pageActions = ob_get_clean();

ob_start();
?>
<?php include __DIR__ . '/../src/partials/page-header.php'; ?>

<!-- Лента: карточки -->
<div class="feed-panel">
  <div class="stack">

    <?php
    // Пост 1
    $bookCover = 'https://placehold.co/80x112?text=Book';
    $bookTitle = 'Name of book';
    $bookAuthor = 'Author'; // Если в card-book нужен автор
    $userInitials = 'UN';
    $userAvatar = null;
    $userName = 'username';
    $text = 'some text about life and many more things some text about life and many more things some text about life and many more things some text about life and many more things some text about life and many more things.';
    $likes = 0; // или сколько нужно
    $comments = 0;
    $date = '10.09.2026';
    include __DIR__ . '/../src/components/card-feed.php';
    ?>

    <?php
    // Пост 2
    $bookCover = 'https://placehold.co/80x112?text=Book';
    $bookTitle = 'Name of book';
    $bookAuthor = 'Author';
    $userInitials = 'ST';
    $userName = 'sername';
    $text = 'some text about life and many more things some text about life and many more things some text about life and many more things some text about life and many more things.';
    $likes = 0;
    $comments = 0;
    $date = '10.09.2026';
    include __DIR__ . '/../src/components/card-feed.php';
    ?>

    <?php
    // Пост 3
    $bookCover = 'https://placehold.co/80x112?text=Book';
    $bookTitle = 'Name of book';
    $bookAuthor = 'Author';
    $userInitials = 'ST';
    $userName = 'sername';
    $text = 'some text about life and many more things some text about life and many more things some text about life and many more things some text about life and many more things.';
    $likes = 0;
    $comments = 0;
    $date = '10.09.2026';
    include __DIR__ . '/../src/components/card-feed.php';
    ?>

  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../src/layout.php';