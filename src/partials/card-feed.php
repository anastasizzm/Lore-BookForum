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
 *   $userInitials  — инициалы
 *   $userAvatar    — ключ аватара из БД: 'cat', 'fox', ... или 'default'
 *                    (как его показать — решает partial avatar.php)
 *   $userName      — имя пользователя
 *   $text          — текст поста/коммента
 *   $likes         — число лайков
 *   $comments      — число комментариев
 *   $date          — дата строкой
 *   $currentUserInitials — инициалы текущего пользователя (для своих комментариев)
 *   $currentUserName     — логин текущего пользователя
 *   $currentUserAvatar   — ключ аватара текущего пользователя
 */

// Безопасные значения по умолчанию
$postId        = (int) ($postId        ?? 0);
$publicationId = (int) ($publicationId ?? 0);
$withBook      = $withBook     ?? false;
$bookCover     = $bookCover    ?? '';
$bookTitle     = $bookTitle    ?? '';
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

// «Мой лайк» приходит из PostContext (isLiked) — API /api/posts отдаёт его
// вместе с каждым постом. Раньше состояние хранилось в localStorage и расходилось с БД.
$liked = (bool) ($liked ?? false);
?>
<!-- data-publication-id — publicationId для ответа (comments.js),
     data-cu-* — текущий юзер для оптимистичной вставки своего комментария -->
<article class="card-base card-feed" data-post-id="<?= $postId ?>"
         data-publication-id="<?= $publicationId ?>"
         data-cu-initials="<?= $view->e($currentUserInitials) ?>"
         data-cu-name="<?= $view->e($currentUserName) ?>"
         data-cu-id="<?= $currentUserId ?>"
         data-cu-avatar="<?= $view->e($currentUserAvatar) ?>">

  <?php if ($withBook): ?>
    <div class="card-feed__book-header">
      <?php if ($bookCover !== ''): ?>
        <?php /* не загрузится — app.js заменит <img> на CSS-заглушку */ ?>
        <img src="<?= $view->e($bookCover) ?>" alt="" class="card-feed__book-thumb">
      <?php else: ?>
        <span class="card-feed__book-thumb cover--empty" aria-hidden="true"></span>
      <?php endif; ?>
      <h3 class="card-feed__book-title"><?= $view->e($bookTitle) ?></h3>
    </div>
  <?php endif; ?>

  <div class="card-feed__body-section<?= $withBook ? ' card-feed__body-section--with-book' : '' ?>">
    <!--
      Пост = корневой комментарий (макет ленты): та же строка, что и у
      комментариев ниже — 32px аватар, синий ник, тёмно-синий текст,
      серая дата + синий Reply, сердце справа на уровне ника.
      Reply раскрывает встроенный ввод (POST /api/posts/{postId}).
    -->
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
              <div class="comment-card__author"><?= $view->e($userName) ?></div>
              <div class="comment-card__text"><?= nl2br($view->e($text)) ?></div>
            </div>

            <!-- Лайк поста — та же кнопка, что у комментариев (card-feed.js) -->
            <button type="button" class="btn-icon-small btn-like comment-card__like"
                    data-like-btn data-liked="<?= $liked ? '1' : '0' ?>"
                    aria-pressed="<?= $liked ? 'true' : 'false' ?>" aria-label="Like">
              <svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span data-like-count><?= (int)$likes ?></span>
            </button>
          </div>

          <div class="comment-card__footer">
            <div class="comment-card__meta">
              <span><?= $view->e($date) ?></span>
              <!-- Reply открывает/закрывает встроенный ввод (card-feed.js) -->
              <button type="button" class="comment-card__reply"
                      data-comment-toggle aria-expanded="false" aria-label="Reply">Reply</button>
            </div>
          </div>

          <!--
            Ответ на пост: POST /api/posts/{postId}, application/x-www-form-urlencoded
              поля: content, publicationId, csrf-поле
              успех: 201 {"createdId": N}
              ошибка: не-201, JSON с errors / message
            Отправку делает card-feed.js (fetch); форма вшита в строку поста
            и раскрывается кнопкой Reply (без разделителя сверху).
          -->
          <form class="comment-form" action="/api/posts/<?= $postId ?>" method="POST"
                data-feed-comment-form data-post-id="<?= $postId ?>" novalidate hidden>
            <?= $view->csrfField() ?>
            <input type="hidden" name="publicationId" value="<?= $publicationId ?>">
            <input type="text" class="comment-form__input" name="content" placeholder="Add a comment…" maxlength="500" autocomplete="off">
            <button type="submit" class="comment-form__submit" disabled>Post</button>
            <p data-comment-error role="alert" hidden
               style="color: red; margin-top: 8px; font-size: 14px; width: 100%;"></p>
            <p data-comment-status role="status" hidden
               style="color: green; margin-top: 8px; font-size: 14px; width: 100%;"></p>
          </form>

          <!-- Маленькая серая ссылка раскрытия — вместо пилюли «Show less» внизу -->
          <button type="button" class="comment-card__more" data-fc-expand
                  aria-expanded="false">Show more</button>

          <!-- Счётчик нужен card-feed.js (ленивая загрузка), по макету не виден -->
          <span class="visually-hidden" data-comment-count><?= (int)$comments ?></span>

          <!--
            Комментарии к посту: GET /api/posts?parent={postId}.
            Подгружаются лениво при первом раскрытии (card-feed.js), порядок —
            хронологический; кнопки «Show more/less comments» ставит comments.js.
            Блок вложен в колонку контента поста — уходит вправо под ник,
            как ответы; одна общая линия слева — у body-section--with-book.
          -->
          <div class="feed-comments" data-feed-comments hidden>
            <div class="feed-comments__list" data-fc-list></div>
            <p class="feed-comments__status" data-fc-status role="status" hidden></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>