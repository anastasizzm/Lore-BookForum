<?php
/**
 * Ожидает:
 *   $cover    — URL обложки
 *   $title    — название книги
 *   $author   — автор
 *   $likes    — число лайков
 *   $comments — число комментариев
 */
?>
<article class="card-base card-book">
  <div class="card-book__cover">
    <img src="<?= htmlspecialchars($cover ?? '') ?>" alt="">
  </div>

  <h3 class="card-book__title"><?= htmlspecialchars($title) ?></h3>
  <p class="card-book__author"><?= htmlspecialchars($author) ?></p>

  <div class="card-book__tags">
    <span class="tag">
      <span class="tag__icon">♡</span>
      <span class="tag__value"><?= (int)$likes ?></span>
    </span>
    <span class="tag">
      <span class="tag__icon">💬</span>
      <span class="tag__value"><?= (int)$comments ?></span>
    </span>
  </div>
</article>