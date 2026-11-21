<?php
/**
 * article-rights-dialog — всплывающее окно выбора прав при публикации статьи.
 *
 * Разметка статична. Людей и роли сохраняет roles-module.js отдельными
 * запросами (/api/publications/{id}/users|roles); отдельного списка
 * пользователей/GET /api/articles/{id}/rights нет, editor_ids[] не используется.
 *
 * Публикует статью кнопка в окне:
 *   POST /api/articles/{id}/publish  (пустое тело)
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

    <!-- Модуль «назначение ролей» (roles-module.php) — единственный UI
         людей/ролей. Экран 2 «Publish» зовёт submit() хоста
         (article-publish.js). Активен при data-rights-view="roles". -->
    <?php $view->include('roles-module'); ?>

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