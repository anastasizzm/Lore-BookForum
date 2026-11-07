<?php
/**
 * article-reader — оверлей чтения standalone-статьи.
 *
 * Открывается кнопкой «Читать статью» на /articles/{id}
 * (data-article-reader-open, public/assets/js/article-reader.js).
 * Для отрывка из книги кнопка вместо оверлея ведёт на /books/{id}
 * (см. article-details.php).
 *
 * @param string $title   — заголовок статьи
 * @param string $content — полный текст статьи (многострочный)
 */
$title   = (string) ($title   ?? '');
$content = (string) ($content ?? '');
$tr      = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
?>
<div class="modal article-reader" data-article-reader hidden>
  <div class="modal__backdrop" data-article-reader-dismiss></div>

  <div class="modal__dialog article-reader__dialog"
       role="dialog"
       aria-modal="true"
       aria-labelledby="articleReaderTitle">
    <div class="article-reader__head">
      <h2 class="modal__title article-reader__title" id="articleReaderTitle">
        <?= $view->e($title) ?>
      </h2>
      <button type="button"
              class="btn-icon article-reader__close"
              data-article-reader-close
              aria-label="<?= $tr('common.common.close') ?>">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <div class="article-reader__body article-content" data-article-reader-body>
      <?php if (trim($content) === ''): ?>
        <p><?= $tr('common.article.no_text') ?></p>
      <?php else: ?>
        <?php foreach (preg_split('/\R{2,}/u', trim($content)) as $para): ?>
          <?php if (trim($para) === '') continue; ?>
          <p><?= nl2br($view->e(trim($para))) ?></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="modal__actions">
      <button type="button" class="btn btn--secondary" data-article-reader-close>
        <?= $tr('common.common.close') ?>
      </button>
    </div>
  </div>
</div>
