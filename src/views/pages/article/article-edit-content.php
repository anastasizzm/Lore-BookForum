<?php
/**
 * article-edit-content.php — страница правки КОНТЕНТА статьи.
 *
 * Подключается из article-form.php при GET /articles/new?id=N&part=content
 * (новых роутов не добавляем — диспетчеризация по $_GET['part'] в
 * article-form.php). Все helpers ($val / $errorBox / $withLang /
 * $langField / $richEditor и т.д.) уже выполнены до точки подключения.
 *
 * ПОЛЯ — копия шага 2 article-form.php: аннотация, заметки автора и
 * редактор текста статьи (rich-editor, standalone-режим).
 *
 * ТОЛЬКО РАЗМЕТКА: префилла и отправки пока нет — article-form.js и
 * article-text.js на эту страницу не подключаются, поэтому тулбар
 * редактора и счётчик символов останутся нерабочими до подключения JS.
 * Кнопка «Сохранить» отрендерена СКРЫТОЙ и БЕЗ data-requires —
 * permissions.js её не покажет, пока не появится обработчик отправки.
 * <form> с onsubmit="return false;" блокирует нативный submit (Enter
 * в поле).
 *
 * Ссылки «Изменить контент» ведут сюда из details-menu.php.
 */
?>

<?php $view->startBlock('title'); ?><?= $tr('common.roles.edit_content') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/forms/article-form.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.roles.edit_content'),
    'subtitle' => $view->t('common.articleform.subtitle'),
]); ?>

<?php if (!empty($unboundErrors)): ?>
  <div class="messages">
    <?php foreach ($unboundErrors as $one): ?>
      <div class="message message--error">
        <div class="message__body"><?= $view->e($one) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="article-form-grid">

  <section class="article-form card-base">

    <!-- Тот же контракт, что у article-form.php (POST /api/articles,
         multipart + CSRF, ?lang= в action); отправка заблокирована
         onsubmit="return false;". -->
    <form action="<?= $view->e($action) ?>"
          method="POST"
          id="articleForm"
          data-article-form
          data-edit-part="content"
          data-book-title="<?= $view->e($bookTitle) ?>"
          onsubmit="return false;"
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>
      <?= $langField ?>
      <input type="hidden" name="id" value="<?= $articleId ?>">
      <input type="hidden" name="remove_cover" value="">

      <!-- ============================================================
           КОНТЕНТ (бывший шаг 2 статьи)
           ============================================================ -->
      <section class="article-step" data-step="2">
        <header class="article-step__header">
          <h2 class="article-step__title"><?= $tr('common.articleform.step2_title') ?></h2>
        </header>

        <h3 class="article-form__section-title"><?= $tr('common.articleform.section_text') ?></h3>

        <div class="form-field">
          <label for="articleAnnotation"><?= $tr('common.articleform.field_annotation') ?></label>
          <textarea class="form-field__input form-field__input--textarea"
                    id="articleAnnotation"
                    name="annotation"
                    rows="6"
                    maxlength="5000"
                    placeholder="<?= $tr('common.articleform.annotation_ph') ?>"><?= $view->e($val('annotation')) ?></textarea>
          <?= $errorBox('annotation') ?>
        </div>

        <div class="form-field">
          <label for="articleAuthorNote"><?= $tr('common.articleform.field_author_note') ?></label>
          <textarea class="form-field__input form-field__input--textarea"
                    id="articleAuthorNote"
                    name="author_note"
                    rows="4"
                    maxlength="2000"
                    placeholder="<?= $tr('common.articleform.author_note_ph') ?>"><?= $view->e($val('author_note')) ?></textarea>
          <?= $errorBox('author_note') ?>
        </div>

        <div data-standalone-only>
          <?php $richEditor('content', 'articleContent', 'common.articleform.field_content',
                            'common.articleform.content_ph', 'lg', $toolbarFull); ?>
        </div>

      </section>

      <!-- ============================================================
           НИЖНЯЯ ПАНЕЛЬ: «Отмена» ведёт на страницу статьи, «Сохранить»
           СКРЫТА без data-requires — permissions.js её не покажет,
           пока не подключим префилл и сохранение (PATCH /api/articles/{id}
           + PUT /api/articles/{id}/content).
           ============================================================ -->
      <div class="article-form__actions">
        <div class="article-form__buttons">
          <a class="btn btn--secondary" href="<?= $view->e($withLang('/articles/' . $articleId)) ?>"><?= $tr('common.cancel') ?></a>

          <button type="submit" class="btn btn--primary" data-submit-save hidden>
            <?= $tr('common.articleform.save') ?>
          </button>
        </div>
      </div>

    </form>

  </section>

</div>

<?php $view->endBlock('content'); ?>
