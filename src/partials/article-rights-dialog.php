<?php
/**
 * article-rights-dialog — всплывающее окно выбора прав при публикации статьи.
 *
 * Разметка статична; список пользователей и выбранные editor_ids
 * подгружает JS (article-publish.js) через:
 *   GET /api/users?page=&q=
 *   GET /api/articles/{id}/rights   (404 — ок без прав)
 *
 * Публикует статья уже кнопка в окне:
 *   POST /api/articles/{id}/publish  (multipart: editor_ids[])
 */
$tr = static fn(string $k, array $p = []): string => $view->e($view->t($k, $p));
?>
<div class="article-rights" data-article-rights role="dialog" aria-modal="true"
     aria-labelledby="articleRightsTitle" hidden>

  <div class="article-rights__backdrop" data-rights-backdrop></div>

  <div class="article-rights__panel">

    <header class="article-rights__head">
      <h2 class="article-rights__title" id="articleRightsTitle">
        <?= $tr('common.articleform.publish_title') ?>
      </h2>
      <button type="button" class="article-rights__close" data-rights-close
              aria-label="<?= $tr('common.articleform.picker_close') ?>">&times;</button>
    </header>

    <p class="article-rights__hint"><?= $tr('common.articleform.publish_subtitle') ?></p>

    <div class="article-rights__search">
      <input type="search"
             class="form-field__input"
             data-rights-search
             autocomplete="off"
             placeholder="<?= $tr('common.articleform.publish_search_ph') ?>">
    </div>

    <div class="article-rights__list" data-rights-list>
      <p class="article-rights__empty" data-rights-status><?= $tr('common.common.loading') ?></p>
    </div>

    <footer class="article-rights__foot">
      <button type="button" class="btn btn--secondary" data-rights-cancel>
        <?= $tr('common.cancel') ?>
      </button>
      <button type="button" class="btn btn--primary" data-rights-submit>
        <?= $tr('common.articleform.publish_submit') ?>
      </button>
    </footer>

  </div>
</div>