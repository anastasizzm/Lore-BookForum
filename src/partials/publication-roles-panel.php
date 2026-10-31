<?php
/**
 * publication-roles-panel — плашка ролей и людей публикации при публикации.
 *
 * Вставляется в окно публикации книги (rights-modal, режим after-publish/
 * publish) и в окно публикации статьи (article-rights-dialog). Показывает
 * бейджи ролей публикации и её участников, когда id уже известен.
 *
 * Данные грузит JS (public/assets/js/publication-roles.js):
 *   GET /api/publications/{id}/roles  → { items: [ { role, pubPermissions, … } ] }
 *   GET /api/publications/{id}/users  → { items: [ { id, username, … } ] }
 * при недоступности роутов — встроенный мок (режим data-pub-roles-api="mock").
 *
 * Использование (в родительском JS, когда id публикации известен):
 *   var root = dialog.querySelector('[data-pub-roles]');
 *   root.setAttribute('data-pub-roles-id', String(publicationId));
 *   LorePubRoles.load(root, publicationId);
 *
 * @param string $title — локализованный заголовок плашки (опционально)
 *
 * Стили — public/assets/css/publication-roles.css.
 */
$title = $title ?? '';
?>
<div class="pub-roles" data-pub-roles data-pub-roles-id="0" data-pub-roles-api="unknown">
  <h3 class="pub-roles__title"><?= $view->e($title !== '' ? $title : $view->t('common.pubroles.title')) ?></h3>

  <p class="pub-roles__mock" data-pub-roles-mock hidden>
    <?= $view->e($view->t('common.pubroles.mock_note')) ?>
  </p>

  <div class="pub-roles__body" data-pub-roles-body hidden>
    <div class="pub-roles__group">
      <span class="pub-roles__label"><?= $view->e($view->t('common.pubroles.roles')) ?></span>
      <ul class="pub-roles__list" data-pub-roles-list></ul>
    </div>
    <div class="pub-roles__group">
      <span class="pub-roles__label"><?= $view->e($view->t('common.pubroles.people')) ?></span>
      <ul class="pub-roles__list" data-pub-people-list></ul>
    </div>
  </div>

  <div class="pub-roles__loading" data-pub-roles-loading hidden>
    <span class="pub-roles__spinner" aria-hidden="true"></span>
    <span><?= $view->e($view->t('common.pubroles.loading')) ?></span>
  </div>

  <div class="pub-roles__error" data-pub-roles-error hidden>
    <p class="pub-roles__error-text" data-pub-roles-error-text></p>
    <button type="button" class="btn btn--secondary" data-pub-roles-retry>
      <?= $view->e($view->t('common.pubroles.retry')) ?>
    </button>
  </div>

  <p class="pub-roles__empty" data-pub-roles-empty hidden>
    <?= $view->e($view->t('common.pubroles.empty')) ?>
  </p>
</div>
