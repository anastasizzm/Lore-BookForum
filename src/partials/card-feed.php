<?php
/**
 * card-feed — карточка поста/коммента.
 * Параметры передаются через $view->include('card-feed', [...])
 *
 * Ожидает:
 *   $withBook     — true/false, показывать ли блок книги
 *   $bookCover    — обложка книги
 *   $bookTitle    — название книги
 *   $userInitials — инициалы
 *   $userAvatar   — URL аватара (опционально)
 *   $userName     — имя пользователя
 *   $text         — текст поста/коммента
 *   $likes        — число лайков
 *   $comments     — число комментариев
 *   $date         — дата строкой
 */

// Безопасные значения по умолчанию
$withBook     = $withBook     ?? false;
$bookCover    = $bookCover    ?? '';
$bookTitle    = $bookTitle    ?? '';
$userInitials = $userInitials ?? '';
$userAvatar   = $userAvatar   ?? null;
$userName     = $userName     ?? '';
$text         = $text         ?? '';
$likes        = $likes        ?? 0;
$comments     = $comments     ?? 0;
$date         = $date         ?? '';
?>
<article class="card-base card-feed">

  <?php if ($withBook): ?>
    <div class="card-feed__book-header">
      <img src="<?= $view->e($bookCover) ?>" alt="" class="card-feed__book-thumb">
      <h3 class="card-feed__book-title"><?= $view->e($bookTitle) ?></h3>
    </div>
  <?php endif; ?>

  <div class="card-feed__body-section">
    <header class="card-feed__head">
      <?php $view->include('avatar', [
          'size'     => 'md',
          'initials' => $userInitials,
          'src'      => $userAvatar,
      ]); ?>
      <div class="card-feed__user">
        <div class="card-feed__name"><?= $view->e($userName) ?></div>
      </div>
    </header>

    <p class="card-feed__text"><?= nl2br($view->e($text)) ?></p>

    <footer class="card-feed__footer">
      <div class="card-feed__tags">
        <span class="tag">
          <span class="tag__icon">♡</span>
          <span class="tag__value"><?= (int)$likes ?></span>
        </span>
        <span class="tag">
          <span class="tag__icon">💬</span>
          <span class="tag__value"><?= (int)$comments ?></span>
        </span>
      </div>
      <span class="card-feed__date"><?= $view->e($date) ?></span>
    </footer>
  </div>
</article>