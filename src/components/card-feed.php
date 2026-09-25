<?php
/**
 * Ожидает:
 *   $bookCover   — обложка книги
 *   $bookTitle   — название книги
 *   $userInitials — инициалы
 *   $userAvatar  — URL аватара (опционально)
 *   $userName    — имя пользователя
 *   $text        — текст поста/коммента
 *   $likes       — число лайков
 *   $comments    — число комментариев
 *   $date        — дата строка
 */
?>
<article class="card-base card-feed">
  <!-- Верхний блок: Книга -->
  <div class="card-feed__book-header">
    <img src="<?= htmlspecialchars($bookCover ?? '') ?>" alt="" class="card-feed__book-thumb">
    <h3 class="card-feed__book-title"><?= htmlspecialchars($bookTitle ?? '') ?></h3>
  </div>

  <!-- Блок с контентом пользователя (сдвинут с линией) -->
  <div class="card-feed__body-section">
    <header class="card-feed__head">
      <?php
        $size = 'md';
        $initials = $userInitials ?? '';
        $src = $userAvatar ?? null;
        include __DIR__ . '/avatar.php';
      ?>
      <div class="card-feed__user">
        <div class="card-feed__name"><?= htmlspecialchars($userName ?? '') ?></div>
      </div>
    </header>

    <p class="card-feed__text"><?= nl2br(htmlspecialchars($text ?? '')) ?></p>

    <footer class="card-feed__footer">
      <div class="card-feed__tags">
        <span class="tag">
          <span class="tag__icon">♡</span>
          <span class="tag__value"><?= (int)($likes ?? 0) ?></span>
        </span>
        <span class="tag">
          <span class="tag__icon">💬</span>
          <span class="tag__value"><?= (int)($comments ?? 0) ?></span>
        </span>
      </div>
      <span class="card-feed__date"><?= htmlspecialchars($date ?? '') ?></span>
    </footer>
  </div>
</article>