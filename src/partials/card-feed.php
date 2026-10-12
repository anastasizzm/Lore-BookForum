<?php
/**
 * card-feed — карточка поста/коммента.
 * Параметры передаются через $view->include('card-feed', [...])
 *
 * Ожидает:
 *   $postId        — id поста (comments.id), на него отправляется ответ
 *   $publicationId — id публикации поста (нужен бэкенду для валидации)
 *   $withBook      — true/false, показывать ли блок книги
 *   $bookCover     — обложка книги
 *   $bookTitle     — название книги
 *   $userId        — id автора поста (ссылка на профиль: /users/{id})
 *   $userInitials  — инициалы
 *   $userAvatar    — ключ аватара из БД: 'cat', 'fox', ... или 'default'
 *   $userName      — имя пользователя
 *   $text          — текст поста/коммента
 *   $likes         — число лайков
 *   $comments      — число комментариев
 *   $date          — дата строкой
 *   $currentUserInitials — инициалы текущего пользователя
 *   $currentUserName     — логин текущего пользователя
 *   $currentUserAvatar   — ключ аватара текущего пользователя
 */

// Короткий помощник: перевод + экранирование (ключи — resources/lang/*/common.json)
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// Безопасные значения по умолчанию
$postId        = (int) ($postId        ?? 0);
$publicationId = (int) ($publicationId ?? 0);
$withBook      = $withBook     ?? false;
$bookCover     = $bookCover    ?? '';
$bookTitle     = $bookTitle    ?? '';
$userId        = (int) ($userId ?? 0);
$userInitials  = $userInitials ?? '';
$userAvatar    = $userAvatar   ?? '';
$userName      = $userName     ?? '';
$text          = $text         ?? '';
$likes         = $likes        ?? 0;
$comments      = $comments     ?? 0;
$date          = $date         ?? '';

// Кто сейчас авторизован — нужно card-feed.js для вставки своего комментария
$currentUserInitials = $currentUserInitials ?? '';
$currentUserName     = $currentUserName     ?? '';
$currentUserId       = (int) ($currentUserId ?? 0);
$currentUserAvatar   = $currentUserAvatar   ?? '';

// «Мой лайк» приходит из PostContext (isLiked)
$liked = (bool) ($liked ?? false);
?>
<article class="card-base card-feed" data-post-id="<?= $postId ?>"
         data-publication-id="<?= $publicationId ?>"
         data-author-id="<?= $userId ?>"
         data-author-username="<?= $view->e($userName) ?>"
         data-cu-initials="<?= $view->e($currentUserInitials) ?>"
         data-cu-name="<?= $view->e($currentUserName) ?>"
         data-cu-id="<?= $currentUserId ?>"
         data-cu-avatar="<?= $view->e($currentUserAvatar) ?>">

  <?php if ($withBook): ?>
    <div class="card-feed__book-header">
      <?php if ($bookCover !== ''): ?>
        <img src="<?= $view->e($bookCover) ?>" alt="" class="card-feed__book-thumb">
      <?php else: ?>
        <span class="card-feed__book-thumb cover--empty" aria-hidden="true"></span>
      <?php endif; ?>
      <h3 class="card-feed__book-title">
        <a class="card-feed__book-link" href="/books/<?= $publicationId ?>"
           data-pub-link data-pub-id="<?= $publicationId ?>"><?= $view->e($bookTitle) ?></a>
      </h3>
    </div>
  <?php endif; ?>

  <div class="card-feed__body-section<?= $withBook ? ' card-feed__body-section--with-book' : '' ?>">
    <div class="card-feed__post">
      <div class="comment-card__inner">
        <?php $view->include('avatar', [
            'size'     => 'sm',
            'initials' => $userInitials,
            'icon'     => $userAvatar,
        ]); ?>
        <div class="comment-card__content">
          <div class="comment-card__main">
            <div class="comment-card__head">
              <div class="comment-card__author">
                <?php if ($userId > 0): ?>
                  <a href="/users/<?= $userId ?>" class="user-link"><?= $view->e($userName) ?></a>
                <?php else: ?>
                  <?= $view->e($userName) ?>
                <?php endif; ?>
              </div>
              <div class="comment-card__text"><?= nl2br($view->e($text)) ?></div>
            </div>
          </div>

          <div class="comment-card__footer">
            <div class="comment-card__meta">
              <!-- Лайк поста -->
              <button type="button" class="btn-icon-small btn-like comment-card__like"
                      data-like-btn data-liked="<?= $liked ? '1' : '0' ?>"
                      aria-pressed="<?= $liked ? 'true' : 'false' ?>"
                      aria-label="<?= $tr('common.comments.like') ?>">
                <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span data-like-count><?= (int)$likes ?></span>
              </button>
              <!-- Reply: открывает/закрывает встроенный ввод -->
              <button type="button" class="comment-card__reply"
                      data-comment-toggle aria-expanded="false"
                      aria-label="<?= $tr('common.comments.reply') ?>"><?= $tr('common.comments.reply') ?></button>
            </div>
            <span class="comment-card__date"><?= $view->e($date) ?></span>
          </div>

          <!-- Ответ на пост -->
          <form class="comment-form" action="/api/posts/<?= $postId ?>" method="POST"
                data-feed-comment-form data-post-id="<?= $postId ?>" novalidate hidden>
            <?= $view->csrfField() ?>
            <input type="hidden" name="publicationId" value="<?= $publicationId ?>">
            <input type="text" class="comment-form__input" name="content"
                   placeholder="<?= $tr('common.comments.add_comment') ?>" maxlength="500" autocomplete="off">
            <button type="submit" class="comment-form__submit" disabled><?= $tr('common.comments.post') ?></button>
            <p data-comment-error role="alert" hidden
               style="color: red; margin-top: 8px; font-size: 14px; width: 100%;"></p>
            <p data-comment-status role="status" hidden
               style="color: green; margin-top: 8px; font-size: 14px; width: 100%;"></p>
          </form>

          <!-- Ссылка «Show more» / «Show less» -->
          <button type="button" class="comment-card__more" data-fc-expand
                  aria-expanded="false"><?= $tr('common.js.show_more') ?></button>

          <span class="visually-hidden" data-comment-count><?= (int)$comments ?></span>

          <div class="feed-comments" data-feed-comments hidden>
            <div class="feed-comments__list" data-fc-list></div>
            <p class="feed-comments__status" data-fc-status role="status" hidden></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>