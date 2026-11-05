<?php
/**
 * article-edit-meta.php — страница правки МЕТАДАННЫХ статьи.
 *
 * Подключается из article-form.php при GET /articles/new?id=N&part=meta
 * (новых роутов не добавляем: роут articles.create рендерит
 * article-form.php, а та диспетчеризует по $_GET['part']). Все helpers
 * ($val / $errorBox / $withLang / $langField / $richEditor и т.д.) уже
 * выполнены до точки подключения.
 *
 * ПОЛЯ — копия шага 1 article-form.php: способ создания (standalone /
 * excerpt), источник отрывка (книга + фрагмент), обложка, название,
 * жанр, тип, DOI. Шагов нет (это одна страница), блок «Восстановить»
 * и кнопка «Далее» — только у создания, здесь их нет.
 *
 * ТОЛЬКО РАЗМЕТКА: префилла и отправки пока нет — article-form.js,
 * article-text.js на эту страницу не подключаются, поэтому справочники
 * жанра/типа, выбор книги, превью обложки и редактор фрагмента остаются
 * нерабочими до подключения JS. Кнопка «Сохранить» отрендерена
 * СКРЫТОЙ и БЕЗ data-requires — permissions.js её не покажет, пока не
 * появится обработчик отправки. <form> с onsubmit="return false;"
 * блокирует нативный submit (Enter в поле).
 *
 * Ссылки «Изменить мета-данные» ведут сюда из details-menu.php
 * (дропдаун «три точки» на странице статьи).
 */
?>

<?php $view->startBlock('title'); ?><?= $tr('common.roles.edit_meta') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/article-form.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.roles.edit_meta'),
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
          data-edit-part="meta"
          data-book-title="<?= $view->e($bookTitle) ?>"
          onsubmit="return false;"
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>
      <?= $langField ?>
      <input type="hidden" name="id" value="<?= $articleId ?>">
      <input type="hidden" name="remove_cover" value="">

      <!-- ============================================================
           МЕТАДАННЫЕ (бывший шаг 1 статьи)
           ============================================================ -->
      <section class="article-step" data-step="1">
        <header class="article-step__header">
          <h2 class="article-step__title"><?= $tr('common.articleform.step1_title') ?></h2>
        </header>

        <!-- Способ создания -->
        <h3 class="article-form__section-title"><?= $tr('common.articleform.section_mode') ?></h3>

        <div class="mode-switch" role="radiogroup" aria-label="<?= $tr('common.articleform.section_mode') ?>">
          <label class="mode-switch__option">
            <input class="mode-switch__radio" type="radio" name="mode" value="standalone"
                   <?= $mode === 'standalone' ? 'checked' : '' ?>>
            <span class="mode-switch__body">
              <span class="mode-switch__title"><?= $tr('common.articleform.mode_standalone') ?></span>
              <span class="mode-switch__hint"><?= $tr('common.articleform.mode_standalone_hint') ?></span>
            </span>
          </label>
          <label class="mode-switch__option">
            <input class="mode-switch__radio" type="radio" name="mode" value="excerpt"
                   <?= $mode === 'excerpt' ? 'checked' : '' ?>>
            <span class="mode-switch__body">
              <span class="mode-switch__title"><?= $tr('common.articleform.mode_excerpt') ?></span>
              <span class="mode-switch__hint"><?= $tr('common.articleform.mode_excerpt_hint') ?></span>
            </span>
          </label>
        </div>

        <!-- Источник (только excerpt) -->
        <div class="article-source" data-excerpt-only <?= $mode === 'excerpt' ? '' : 'hidden' ?>

          <h3 class="article-form__section-title"><?= $tr('common.articleform.section_source') ?></h3>

          <div class="form-field">
            <label for="articleBookSearch"><?= $tr('common.articleform.field_book') ?></label>

            <div class="book-picker" data-book-box>
              <input type="hidden" name="book" value="<?= $bookId > 0 ? $bookId : '' ?>" data-excerpt-field>

              <input class="form-field__input"
                     type="search"
                     id="articleBookSearch"
                     data-book-search
                     autocomplete="off"
                     placeholder="<?= $tr('common.articleform.book_search_ph') ?>">

              <ul class="book-picker__results" data-book-results hidden></ul>

              <div class="book-picker__chip" data-book-selected hidden>
                <span class="book-picker__chip-title" data-book-selected-title></span>
                <button type="button" class="btn btn--secondary" data-book-change><?= $tr('common.articleform.book_change') ?></button>
              </div>
            </div>
            <?= $errorBox('book') ?>
          </div>

          <div class="form-field">
            <span class="form-field__label" id="articleFragmentLabel"><?= $tr('common.articleform.field_fragment') ?></span>

            <div class="fragment-box">
              <p class="fragment-box__summary" data-fragment-summary aria-labelledby="articleFragmentLabel"
                 aria-live="polite"><?= $tr('common.articleform.fragment_empty') ?></p>
              <button type="button" class="btn btn--secondary" data-open-picker disabled>
                <?= $tr('common.articleform.open_picker') ?>
              </button>
            </div>
            <p class="article-form__hint"><?= $tr('common.articleform.fragment_hint') ?></p>
            <?= $errorBox('fragment') ?>

            <?php foreach (['page_start', 'start_offset', 'start_line', 'start_char', 'start_quote',
                            'page_end', 'end_offset', 'end_line', 'end_char', 'end_quote'] as $anchorKey): ?>
              <input type="hidden" name="<?= $anchorKey ?>" value="<?= $view->e($val($anchorKey)) ?>" data-excerpt-field>
            <?php endforeach; ?>
            <input type="hidden" name="anchor_version" value="<?= $view->e($val('anchor_version', '1')) ?>" data-excerpt-field>
          </div>
        </div>

        <!-- Основное -->
        <h3 class="article-form__section-title"><?= $tr('common.articleform.section_main') ?></h3>

        <div class="article-main">

          <div class="form-field article-main__cover">
            <label for="articleCover"><?= $tr('common.articleform.field_cover') ?></label>

            <div class="cover-upload" data-cover-upload>
              <div class="cover-upload__frame">
                <img class="cover-upload__preview"
                     data-cover-preview
                     <?= $coverUrl !== '' ? 'src="' . $view->e($coverUrl) . '"' : 'hidden' ?>
                     alt="<?= $tr('common.articleform.cover_preview_alt') ?>">
                <span class="cover-upload__placeholder" data-cover-placeholder
                      <?= $coverUrl !== '' ? 'hidden' : '' ?>><?= $tr('common.articleform.cover_placeholder') ?></span>
              </div>

              <div class="cover-upload__controls">
                <input class="form-field__input form-field__input--file"
                       type="file"
                       id="articleCover"
                       name="cover"
                       accept="image/jpeg,image/png,image/webp"
                       data-cover-input>
                <p class="article-form__hint"><?= $tr('common.articleform.cover_hint') ?></p>
                <button class="btn btn--secondary"
                        type="button"
                        data-cover-remove
                        <?= $coverUrl !== '' ? '' : 'hidden' ?>><?= $tr('common.articleform.cover_remove') ?></button>
              </div>
            </div>
            <?= $errorBox('cover') ?>
          </div>

          <div class="article-main__fields">

            <div class="form-field">
              <label for="articleTitle"><?= $tr('common.articleform.field_title') ?></label>
              <input class="form-field__input"
                     type="text"
                     id="articleTitle"
                     name="title"
                     value="<?= $view->e($val('title')) ?>"
                     maxlength="200"
                     autocomplete="off"
                     required>
              <?= $errorBox('title') ?>
            </div>

            <div class="form-row--two-cols">

              <div class="form-field">
                <label for="articleGenre"><?= $tr('common.articleform.field_genre') ?></label>
                <select class="form-field__input article-form__select"
                        id="articleGenre"
                        name="genre"
                        data-dynamic-select="genres"
                        data-selected="<?= $view->e($genreValue) ?>"
                        data-placeholder="<?= $tr('common.articleform.select_placeholder') ?>">
                  <?php if ($genreValue !== ''): ?>
                    <option value="<?= $view->e($genreValue) ?>" selected><?= $view->e($genreValue) ?></option>
                  <?php else: ?>
                    <option value="" selected disabled><?= $tr('common.articleform.select_placeholder') ?></option>
                  <?php endif; ?>
                </select>
                <?= $errorBox('genre') ?>
              </div>

              <div class="form-field">
                <label for="articleType"><?= $tr('common.articleform.field_type') ?></label>
                <select class="form-field__input article-form__select"
                        id="articleType"
                        name="type"
                        data-dynamic-select="types"
                        data-selected="<?= $view->e($typeValue) ?>"
                        data-placeholder="<?= $tr('common.articleform.select_placeholder') ?>">
                  <?php if ($typeValue !== ''): ?>
                    <option value="<?= $view->e($typeValue) ?>" selected><?= $view->e($typeValue) ?></option>
                  <?php else: ?>
                    <option value="" selected disabled><?= $tr('common.articleform.select_placeholder') ?></option>
                  <?php endif; ?>
                </select>
                <?= $errorBox('type') ?>
              </div>

            </div>

            <div class="form-field">
              <label for="articleDoi"><?= $tr('common.articleform.field_doi') ?></label>
              <input class="form-field__input"
                     type="text"
                     id="articleDoi"
                     name="doi"
                     value="<?= $view->e($val('doi')) ?>"
                     maxlength="255"
                     autocomplete="off"
                     autocapitalize="off"
                     spellcheck="false"
                     aria-describedby="articleDoiHint"
                     placeholder="<?= $tr('common.articleform.doi_ph') ?>">
              <p class="article-form__hint" id="articleDoiHint"><?= $tr('common.js.doi_hint') ?></p>
              <?= $errorBox('doi') ?>
            </div>

          </div>
        </div>

      </section>

      <!-- ============================================================
           НИЖНЯЯ ПАНЕЛЬ: «Отмена» ведёт на страницу статьи, «Сохранить»
           СКРЫТА без data-requires — permissions.js её не покажет,
           пока не подключим префилл и сохранение.
           ============================================================ -->
      <div class="article-form__actions">
        <div class="article-form__buttons">
          <a class="btn btn--secondary" href="<?= $view->e($withLang('/articles/' . $articleId)) ?>"><?= $tr('common.cancel') ?></a>

          <button type="submit" class="btn btn--primary" data-submit-save hidden>
            <?= $tr('common.articleform.save') ?>
          </button>
        </div>
      </div>

      <!-- ============================================================
           ОКНО ВЫБОРА ФРАГМЕНТА (вне шагов, поверх формы) — копия из
           article-form.php; открывается только article-form.js (сейчас
           на странице его нет, окно остаётся скрытым).
           ============================================================ -->
      <div class="fragment-picker" data-picker role="dialog" aria-modal="true"
           aria-labelledby="articlePickerTitle" hidden>
        <div class="fragment-picker__backdrop" data-picker-backdrop></div>

        <div class="fragment-picker__panel">
          <header class="fragment-picker__head">
            <div class="fragment-picker__head-text">
              <h2 class="fragment-picker__title" id="articlePickerTitle"><?= $tr('common.articleform.picker_title') ?></h2>
              <p class="fragment-picker__book" data-picker-book></p>
            </div>
            <button type="button" class="fragment-picker__close" data-picker-close
                    aria-label="<?= $tr('common.articleform.picker_close') ?>">&times;</button>
          </header>

          <div class="fragment-picker__bar">
            <div class="fragment-picker__group">
              <button type="button" class="fragment-picker__btn" data-picker-prev
                      aria-label="<?= $tr('common.articleform.picker_prev') ?>">&lsaquo;</button>
              <span class="fragment-picker__pages">
                <input class="fragment-picker__pagenum" type="number" min="1" value="1" data-picker-pagenum
                       aria-label="<?= $tr('common.articleform.picker_page') ?>">
                / <span data-picker-total>–</span>
              </span>
              <button type="button" class="fragment-picker__btn" data-picker-next
                      aria-label="<?= $tr('common.articleform.picker_next') ?>">&rsaquo;</button>
            </div>

            <div class="fragment-picker__group">
              <button type="button" class="fragment-picker__btn" data-picker-zoom-out
                      aria-label="<?= $tr('common.articleform.picker_zoom_out') ?>">&minus;</button>
              <span class="fragment-picker__zoom" data-picker-zoomlabel>100%</span>
              <button type="button" class="fragment-picker__btn" data-picker-zoom-in
                      aria-label="<?= $tr('common.articleform.picker_zoom_in') ?>">+</button>
            </div>

            <p class="fragment-picker__busy" data-picker-busy hidden></p>
          </div>

          <p class="fragment-picker__hint"><?= $tr('common.articleform.picker_hint') ?></p>

          <div class="fragment-picker__stage" data-picker-stage>
            <div class="fragment-picker__page" data-picker-page></div>
          </div>

          <footer class="fragment-picker__foot">
            <div class="fragment-picker__points">
              <div class="fragment-picker__point">
                <span class="fragment-picker__state" data-picker-start></span>
                <button type="button" class="btn btn--secondary" data-picker-set="start"><?= $tr('common.articleform.picker_set_start') ?></button>
              </div>
              <div class="fragment-picker__point">
                <span class="fragment-picker__state" data-picker-end></span>
                <button type="button" class="btn btn--secondary" data-picker-set="end"><?= $tr('common.articleform.picker_set_end') ?></button>
              </div>
            </div>

            <div class="fragment-picker__apply">
              <label class="fragment-picker__insert">
                <input type="checkbox" data-picker-insert checked>
                <span><?= $tr('common.articleform.picker_insert') ?></span>
              </label>
              <button type="button" class="btn btn--primary" data-picker-apply disabled><?= $tr('common.articleform.picker_apply') ?></button>
            </div>
          </footer>
        </div>
      </div>

    </form>

  </section>

</div>

<?php $view->endBlock('content'); ?>
