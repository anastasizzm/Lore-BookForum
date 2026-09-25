<?php
// Заглушки — потом заменим на get_all_books()
$titles = [
    'Мастер и Маргарита', 'Преступление и наказание', 'Война и мир',
    'Анна Каренина', 'Идиот', 'Братья Карамазовы',
    'Тихий Дон', 'Доктор Живаго', 'Евгений Онегин',
    'Герой нашего времени', 'Мёртвые души', 'Отцы и дети',
];
$authors = [
    'М. Булгаков', 'Ф. Достоевский', 'Л. Толстой',
    'А. Чехов', 'И. Тургенев', 'М. Шолохов',
    'Б. Пастернак', 'А. Пушкин', 'М. Лермонтов',
    'Н. Гоголь', 'И. Гончаров', 'А. Островский',
];

$books = [];
for ($i = 0; $i < 24; $i++) {
    $books[] = [
        'cover'  => 'https://placehold.co/160x224',
        'title'  => $titles[$i % count($titles)],
        'author' => $authors[$i % count($authors)],
    ];
}
?>
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