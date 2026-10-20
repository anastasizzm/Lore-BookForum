<?php $view->extends('main'); ?>
<?php $view->setBlock('hideFab', '1'); /* правый нижний угол занят — плавающую «+» скрываем */ ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
// Локаль в action и в скрытом поле — как в book-form / profile-edit.
$langQuery = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : null);

$withLang = static function (string $path) use ($langQuery): string {
    if ($langQuery === null || $langQuery === '') return $path;
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . urlencode($langQuery);
};

$langField = $langQuery !== null && $langQuery !== ''
    ? '<input type="hidden" name="lang" value="' . $view->e($langQuery) . '">'
    : '';

/*
 * КОНТРАКТ С БЭКОМ (фронт готов — роуты добавляет команда бэка):
 *
 *   GET  /articles/create      → articles.create,     AuthPolicy::Verified
 *   GET  /articles/{id}/edit   → articles.edit,       только автор статьи
 *        Обе страницы рендерят эту вьюху. Переменные:
 *          $user        — UserContext текущего пользователя (сайдбар);
 *          $form        — значения полей (array; ключи = имена полей ниже), при
 *                         редактировании — из БД, после ошибки — введённое;
 *          $errors      — ['title' => ['msg'], ...] (см. ключи ниже);
 *          $articleId   — int, 0 для новой статьи;
 *          $bookTitle   — название книги-источника (для режима «отрывок»);
 *          $coverUrl    — адрес уже загруженной обложки или '' (редактирование).
 *
 *   POST /articles/save        → articles.save,       AuthPolicy::Verified
 *        ОДИН адрес на создание, редактирование, черновик и автосохранение.
 *        multipart/form-data. Поля: id, status, mode ('standalone'|'excerpt'), title,
 *        genre, type, doi, annotation, author_note, content, cover, remove_cover, book,
 *        page_start, …, anchor_version, check_publish.
 *        status ВСЕГДА 'draft': статья сохраняется как черновик, публикует её только
 *        POST /api/articles/{id}/publish (окно выбора прав, article-publish.js).
 *          • «Create draft»  → status=draft, обязательно только название (title);
 *          • «Publish»       → status=draft + check_publish=1: бэк сохраняет черновик И
 *            проверяет статью как для публикации (жанр, тип, текст/фрагмент…). Не готова →
 *            422 { errors: { field: [msg] } } и фронт возвращает пользователя на шаг с
 *            ошибками; готова → 200, затем открывается окно прав.
 *        Ответ: 200/201 { data: { id, redirect: '/articles/{id}', edit_url: '/articles/{id}/edit' } }.
 *        «Create draft» после успеха ведёт на страницу статьи (redirect): там пометка
 *        «Черновик» и кнопка «Publish» внизу.
 *
 *   POST /api/articles/{id}/publish  → api.articles.publish, AuthPolicy::Verified
 *        multipart: editor_ids[] (ID соавторов-редакторов, можно пусто). Повторно проверяет
 *        статью; успех → { data: { id, redirect } } и статья опубликована.
 *   GET  /api/articles/{id}/rights   → текущие права (для окна): { data: { editor_ids: [..] } },
 *        необязательно (404 фронт игнорирует).
 *   GET  /api/users?page=&q=         → список для окна прав: { items: [{ id, username, name,
 *        surname, email? }], meta: { hasNext } }.
 *   POST /api/articles/images → api.articles.images,  AuthPolicy::Verified
 *        multipart, поле `image` (jpeg/png/webp ≤5MB) → 201 { data: { url } }.
 *
 *   Вьюха страницы статьи (article-details) получает $isDraft и $canManage — см. комментарий
 *   в article-details-draft.snippet.php.
 */
$form   = $form   ?? [];
$errors = $errors ?? [];

$articleId = (int) ($articleId ?? ($form['id'] ?? 0));
$isEdit    = $articleId > 0;
$bookTitle = (string) ($bookTitle ?? '');
$coverUrl  = (string) ($coverUrl ?? '');

$val = static function (string $key, string $default = '') use ($form): string {
    if (array_key_exists($key, $form) && $form[$key] !== null && $form[$key] !== '') {
        return (string) $form[$key];
    }
    return $default;
};

$err = static function (string $key) use ($errors): string {
    $list = $errors[$key] ?? null;
    if ($list === null) return '';
    $first = is_array($list) ? ($list[0] ?? null) : $list;
    return $first === null ? '' : (string) $first;
};

$aliases = [
    'fragment'    => ['page_start', 'page_end', 'start_offset', 'end_offset', 'start_line', 'end_line',
                      'start_char', 'end_char', 'start_quote', 'end_quote'],
    'annotation'  => ['description'],
    'author_note' => ['authorNotes'],
];

$errOf = static function (string $key) use ($err, $aliases): string {
    $first = $err($key);
    if ($first !== '') return $first;
    foreach ($aliases[$key] ?? [] as $alias) {
        $one = $err($alias);
        if ($one !== '') return $one;
    }
    return '';
};

$errorBox = static function (string $key) use ($view, $errOf): string {
    $msg = $errOf($key);
    return '<p class="form-field__error" data-error-for="' . $view->e($key) . '" aria-live="polite">'
        . ($msg !== '' ? '<span class="field-error-icon" title="' . $view->e($msg) . '">!</span>' : '')
        . '</p>';
};

$fieldKeys = ['title', 'genre', 'type', 'doi', 'annotation', 'author_note', 'content', 'cover', 'book', 'fragment'];

$known = array_merge($fieldKeys, ['mode', 'id', 'status', 'remove_cover', 'anchor_version'], ...array_values($aliases));
$unboundErrors = [];
foreach ($errors as $field => $list) {
    if (in_array($field, $known, true)) continue;
    foreach ((array) $list as $one) $unboundErrors[] = $one;
}

$dirtyFields = [];
foreach ($fieldKeys as $key) {
    if ($errOf($key) !== '') $dirtyFields[] = $key;
}

$bookId = (int) $val('book', '0');
$mode   = $val('mode', $bookId > 0 ? 'excerpt' : 'standalone');
if (!in_array($mode, ['standalone', 'excerpt'], true)) $mode = 'standalone';

$genreValue = $val('genre');
$typeValue  = $val('type');

$action = $withLang('/articles/save');

/* ---------- панель редактора ---------- */
$toolbarButtons = [
    'h2'                  => ['tb_h2',     'H2'],
    'h3'                  => ['tb_h3',     'H3'],
    'bold'                => ['tb_bold',   '<strong>B</strong>'],
    'italic'              => ['tb_italic', '<em>I</em>'],
    'quote'               => ['tb_quote',  '“”'],
    'insertUnorderedList' => ['tb_ul',     '• —'],
    'insertOrderedList'   => ['tb_ol',     '1.'],
    'link'                => ['tb_link',   '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6.5 9.5l3-3M5 7L3.7 8.3a2.5 2.5 0 003.5 3.5L8.5 10.5M11 9l1.3-1.3a2.5 2.5 0 00-3.5-3.5L7.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
    'image'               => ['tb_image',  '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="M21 16l-5-5-8 8"/></svg>'],
    'clear'               => ['tb_clear',  'T<sub>x</sub>'],
];
$toolbarFull    = ['h2', 'h3', 'bold', 'italic', 'quote', 'insertUnorderedList', 'insertOrderedList', 'link', 'image', 'clear'];
$toolbarCompact = ['bold', 'italic', 'insertUnorderedList', 'insertOrderedList', 'link'];

$richEditor = static function (
    string $name, string $id, string $labelKey, string $placeholderKey, string $size, array $buttons
) use ($view, $tr, $val, $errorBox, $toolbarButtons): void {
    ?>
    <div class="form-field rich-editor rich-editor--<?= $view->e($size) ?>" data-rich="<?= $view->e($name) ?>">
      <label for="<?= $view->e($id) ?>" id="<?= $view->e($id) ?>Label"><?= $tr($labelKey) ?></label>

      <div class="rich-editor__toolbar" data-editor-toolbar role="toolbar"
           aria-label="<?= $tr('common.articleform.toolbar') ?>">
        <?php foreach ($buttons as $cmd): [$titleKey, $inner] = $toolbarButtons[$cmd]; ?>
          <button type="button"
                  class="rich-editor__btn"
                  data-cmd="<?= $view->e($cmd) ?>"
                  title="<?= $tr('common.articleform.' . $titleKey) ?>"
                  aria-label="<?= $tr('common.articleform.' . $titleKey) ?>"><?= $inner ?></button>
        <?php endforeach; ?>
      </div>

      <div class="rich-editor__area form-field__input"
           id="<?= $view->e($id) ?>Area"
           data-editor
           contenteditable="true"
           role="textbox"
           aria-multiline="true"
           aria-labelledby="<?= $view->e($id) ?>Label"
           data-placeholder="<?= $tr($placeholderKey) ?>"></div>

      <input type="file" hidden data-image-input
             accept="image/jpeg,image/png,image/webp" multiple>

      <textarea class="form-field__input form-field__input--textarea rich-editor__field"
                id="<?= $view->e($id) ?>"
                name="<?= $view->e($name) ?>"
                rows="<?= $size === 'lg' ? 16 : 5 ?>"><?= $view->e($val($name)) ?></textarea>

      <p class="rich-editor__counter" data-editor-counter></p>
      <?= $errorBox($name) ?>
    </div>
    <?php
};
?>

<?php $view->startBlock('title'); ?><?= $tr($isEdit ? 'common.articleform.title_edit' : 'common.articleform.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/article-form.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/article-rights.css') ?>">
  <script src="<?= $view->asset('js/article-publish.js') ?>" defer></script>
  <script src="<?= $view->asset('js/article-text.js') ?>" defer></script>
  <script src="<?= $view->asset('js/article-form.js') ?>" defer></script>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t($isEdit ? 'common.articleform.title_edit' : 'common.articleform.title'),
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

    <form action="<?= $view->e($action) ?>"
          method="POST"
          id="articleForm"
          data-article-form
          data-initial-errors="<?= $view->e(implode(',', $dirtyFields)) ?>"
          data-book-title="<?= $view->e($bookTitle) ?>"
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>
      <?= $langField ?>
      <input type="hidden" name="id" value="<?= $isEdit ? $articleId : '' ?>">
      <input type="hidden" name="remove_cover" value="">

      <!-- ============================================================
           ШАГ 1. ОСНОВНЫЕ ДАННЫЕ
           ============================================================ -->
      <section class="article-step" data-step="1">
        <header class="article-step__header">
          <span class="article-step__num">1 / 2</span>
          <h2 class="article-step__title"><?= $tr('common.articleform.step1_title') ?></h2>
        </header>

        <!-- Найдена локальная копия несохранённого текста -->
        <div class="article-form__restore" data-restore role="status" hidden>
          <p class="article-form__restore-text"><?= $tr('common.articleform.restore_text') ?></p>
          <div class="article-form__restore-actions">
            <button type="button" class="btn btn--primary" data-restore-apply><?= $tr('common.articleform.restore_apply') ?></button>
            <button type="button" class="btn btn--secondary" data-restore-discard><?= $tr('common.articleform.restore_discard') ?></button>
          </div>
        </div>

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
        <div class="article-source" data-excerpt-only <?= $mode === 'excerpt' ? '' : 'hidden' ?>>

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

        <div class="article-step__nav">
          <button type="button" class="btn btn--primary" data-step-next>
            <?= $tr('common.articleform.next') ?> →
          </button>
        </div>
      </section>

      <!-- ============================================================
           ШАГ 2. СОДЕРЖАНИЕ
           ============================================================ -->
      <section class="article-step" data-step="2" hidden>
        <header class="article-step__header">
          <span class="article-step__num">2 / 2</span>
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

        <div data-standalone-only <?= $mode === 'excerpt' ? 'hidden' : '' ?>>
          <?php $richEditor('content', 'articleContent', 'common.articleform.field_content',
                            'common.articleform.content_ph', 'lg', $toolbarFull); ?>
        </div>

        <!-- Действия. «Create draft» сохраняет черновик и открывает страницу статьи;
             «Publish» сначала проверяет статью на бэке, затем открывает окно выбора прав
             (partial article-rights-dialog + article-publish.js). -->
        <div class="article-form__actions">
          <p class="article-form__draft-status" data-draft-status aria-live="polite"></p>
          <div class="article-form__buttons">
            <button type="button" class="btn btn--secondary" data-step-prev>
              ← <?= $tr('common.articleform.back') ?>
            </button>
            <a class="btn btn--secondary" href="<?= $view->e($withLang($view->url('articles'))) ?>"><?= $tr('common.cancel') ?></a>
            <button class="btn btn--secondary" type="submit" name="status" value="draft" data-submit-draft><?= $tr('common.articleform.create_draft') ?></button>
            <button class="btn btn--primary" type="submit" name="status" value="published" data-submit-publish><?= $tr('common.articleform.publish') ?></button>
          </div>
        </div>
      </section>

      <!-- ============================================================
           ОКНО ВЫБОРА ФРАГМЕНТА (вне шагов, поверх формы)
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

<?php // Окно выбора прав при публикации (права подгружаются асинхронно) ?>
<?php $view->include('article-rights-dialog'); ?>

<?php $view->endBlock('content'); ?>