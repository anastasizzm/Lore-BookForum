<?php
/**
 * comment-menu — меню «три точки» у поста / комментария / ответа
 * (лента, страницы деталей, профиль; серверная отрисовка и <template>).
 *
 * Один partial вместо копипасты в десяти местах: удаление (только автор)
 * и жалоба (всем) всегда вместе.
 *
 * @param string $variant — 'post' | 'comment' | 'reply':
 *   'post'    — пост в ленте/профиле (корень .card-feed[data-post-id]);
 *   'comment' — карточка комментария (корень .comment-card[data-comment-id]);
 *   'reply'   — ответ (корень .comment-reply[data-reply-id], лишний класс
 *               comment-reply__actions на врапе).
 *
 * ПОВЕДЕНИЕ (public/assets/js/comments.js):
 *   - врап [data-c-menu-wrap] показывается только автору: после удаления
 *     пункта «Пожаловаться» в меню осталось лишь удаление;
 *   - пункт [data-c-delete] показывает только автор (syncOwnMenu /
 *     syncPostMenu / прогон по уже отрисованному DOM на старте).
 *
 * «Пожаловаться» временно убрана: POST /api/reports отсутствует, а
 * mock-отправка давала ложный успех (FRONTEND-FIXES F-11). Плашка
 * report-modal.php и report-modal.js остаются в проекте.
 *
 * Стили — public/assets/css/component.css (.comment-card__menu*).
 */
$variant  = $variant ?? 'comment';
$isReply  = $variant === 'reply';
$kind     = in_array($variant, ['post', 'comment', 'reply'], true) ? $variant : 'comment';
$wrapClass = 'comment-card__actions' . ($isReply ? ' comment-reply__actions' : '');
?>
<div class="<?= $view->e($wrapClass) ?>" data-c-menu-wrap hidden>
  <button type="button" class="btn-icon-small comment-card__menu-btn" data-c-menu-toggle
          aria-haspopup="true" aria-expanded="false"
          aria-label="<?= $view->e($view->t('common.comments.options')) ?>">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <circle cx="8" cy="3" r="1.5"/>
      <circle cx="8" cy="8" r="1.5"/>
      <circle cx="8" cy="13" r="1.5"/>
    </svg>
  </button>
  <div class="comment-card__menu" data-c-menu hidden>
    <!-- Удаление — только автору: comments.js прячет пункт, если
         data-author-id корня не совпал с моим id -->
    <button type="button" class="comment-card__menu-item comment-card__menu-item--danger"
            data-c-delete><?= $view->e($view->t('common.js.delete')) ?></button>
  </div>
</div>
