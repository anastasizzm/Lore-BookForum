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

  <div class="card-feed__body-section<?= $withBook ? ' card-feed__body-section--with-book' : '' ?>">
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
    <div class="card-feed__actions">
        <button type="button" class="action-btn action-like" data-like-btn aria-pressed="false" aria-label="Like">
            <svg width="18" height="16" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span data-like-count><?= (int)$likes ?></span>
        </button>

        <button type="button" class="action-btn action-comment" data-comment-toggle aria-expanded="false" aria-label="Comment">
            <svg width="18" height="16" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M2.04054 14.5711C2.12274 14.2301 2.09137 13.8745 1.95045 13.5499C0.969772 11.6921 0.739261 9.57774 1.29958 7.57989C1.85991 5.58204 3.17505 3.82907 5.01299 2.63027C6.85092 1.43147 9.09353 0.863883 11.3451 1.02764C13.5967 1.1914 15.7127 2.07599 17.3196 3.52534C18.9264 4.97468 19.9211 6.89564 20.1279 8.94929C20.3348 11.0029 19.7406 13.0573 18.4501 14.7499C17.1597 16.4425 15.256 17.6646 13.0748 18.2006C10.8937 18.7366 8.57534 18.5519 6.52876 17.6793C6.19287 17.5629 5.8279 17.535 5.47547 17.5988L2.20442 18.4721C2.04663 18.5104 1.88076 18.5112 1.72254 18.4744C1.56431 18.4377 1.41898 18.3647 1.30033 18.2623C1.18167 18.16 1.09362 18.0316 1.04453 17.8895C0.995446 17.7473 0.986942 17.5961 1.01983 17.4501L2.04054 14.5711Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span data-comment-count><?= (int)$comments ?></span>
        </button>
    </div>
    <span class="card-feed__date"><?= $view->e($date) ?></span>
</footer>

<form class="comment-form" data-comment-form hidden>
    <input type="text" class="comment-form__input" placeholder="Add a comment…" maxlength="500" autocomplete="off">
    <button type="submit" class="comment-form__submit" disabled>Post</button>
</form>
  </div>
</article>