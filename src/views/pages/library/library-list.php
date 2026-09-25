<?php
// Заглушки — потом заменим на get_all_books()
$books = $books ?? [];
if (empty($books)) {
    for ($i = 1; $i <= 24; $i++) {
        $books[] = [
            'cover'  => 'https://placehold.co/160x224',
            'title'  => 'Name of book ' . $i,
            'author' => 'Author',
        ];
    }
}
$books_count = count($books);
?>

<section class="books-panel">

  <header class="books-panel__head">
    <div class="books-panel__title-wrap">
      <div class="books-panel__icon">
        <svg width="20" height="20" viewBox="0 0 17 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M2 2.5C2 1.83696 2.21401 1.20107 2.59494 0.732233C2.97587 0.263392 3.49253 0 4.03125 0L12.1562 0C12.695 0 13.2116 0.263392 13.5926 0.732233C13.9735 1.20107 14.1875 1.83696 14.1875 2.5V19.375C14.1875 19.4881 14.1625 19.599 14.1153 19.6959C14.0681 19.7929 14.0004 19.8723 13.9194 19.9257C13.8384 19.979 13.7472 20.0044 13.6554 19.999C13.5637 19.9936 13.4748 19.9576 13.3984 19.895L8.09375 16.3762L2.78914 19.895C2.71267 19.9576 2.62383 19.9936 2.53208 19.999C2.44033 20.0044 2.3491 19.979 2.26812 19.9257C2.18714 19.8723 2.11944 19.7929 2.07223 19.6959C2.02501 19.599 2.00005 19.4881 2 19.375V2.5ZM4.03125 1.25C3.76189 1.25 3.50356 1.3817 3.31309 1.61612C3.12263 1.85054 3.01562 2.16848 3.01562 2.5V18.2075L7.81242 15.105C7.89576 15.0367 7.99364 15.0003 8.09375 15.0003C8.19386 15.0003 8.29174 15.0367 8.37508 15.105L13.1719 18.2075V2.5C13.1719 2.16848 13.0649 1.85054 12.8744 1.61612C12.6839 1.3817 12.4256 1.25 12.1562 1.25H4.03125Z" fill="currentColor"/>
        </svg>
      </div>
      <div>
        <h2 class="books-panel__title">All books</h2>
        <p class="books-panel__meta">
          <?= $books_count ?> items · Updated today
        </p>
      </div>
    </div>

    <?php
    $sort_options = [
        'popularity' => 'Popularity',
        'newest'     => 'Newest',
        'title'      => 'A → Z',
    ];

    $current_sort  = $_GET['sort'] ?? 'popularity';
    $current_label = $sort_options[$current_sort] ?? 'Popularity';

    $label = 'Sort: ' . $current_label;

    $options = [];
    foreach ($sort_options as $key => $text) {
        $options[] = [
            'label' => $text,
            'href'  => '?sort=' . urlencode($key),
        ];
    }
    include __DIR__ . '/../../../components/dropdown.php';
    ?>
  </header>

  <div class="grid-books">
    <?php foreach ($books as $book): ?>
      <?php
        $cover  = $book['cover'];
        $title  = $book['title'];
        $author = $book['author'];
        include __DIR__ . '/../../../components/card-book.php';
      ?>
    <?php endforeach; ?>
  </div>

</section>