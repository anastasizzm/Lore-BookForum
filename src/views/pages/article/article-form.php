<?php
/**
 * article-form — создание статьи (заготовка, роута пока нет).
 *
 * Сделано по аналогии с book-form.php (ветка feature/book-form-p0),
 * но проще: одна книга-обложка, без списков авторов/редакторов.
 *
 * КОНТРАКТ С БЭКОМ (ожидается, роутов сейчас НЕТ):
 *   GET  /articles/create → роут articles.create
 *        рендерит эту вьюху, передавая $user (для сайдбара);
 *        при повторном рендере после 422 — $form (значения полей) и
 *        $errors (['title' => ['msg'], 'cover' => [...], ...]).
 *   POST /articles/store  → роут articles.store (ИМЯ ПОДТВЕРДИТЬ С БЭКОМ!)
 *        enctype=multipart/form-data, поля:
 *          title (string, required, ≤255)        — publications.title
 *          description (string, ≤4000)           — publications.description
 *          genre (int id, required)              — GET /api/additional/genres
 *          doi (string, optional, формат 10.x/y) — articles.doi
 *          type ('content' | 'book')             — articles.type
 *          content (text, обязателен при type=content)
 *          book_id (int, обязателен при type=book) + page_start, page_end
 *          cover (file: jpeg/png/webp, ≤5MB, optional) — publications.icon
 *          _token (CSRF), lang
 *        успех: Accept: application/json → 201 { data: { id, redirect } }
 *        ошибки: 422 { errors: { field: [msg, ...] } }
 *
 * ПОКА: action="#" — роут articles.store не существует, а $view->url() на
 * неизвестном имени бросает RuntimeException. Когда бэк добавит роут,
 * заменить на action="<?= $view->e($withLang('/articles/store')) ?>" —
 * как в book-form.php (там путь захардкожен по той же причине).
 *
 * Разметка + fetch + FormData + превью обложки — public/assets/js/article-form.js;
 * без JS форма уходит обычным POST (прогрессивное улучшение).
 *
 * Проверок прав здесь нет: бэк ещё не назвал финальные имена разрешений.
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

$form   = $form   ?? [];
$errors = $errors ?? [];

// Сохраняем локаль в ссылках — иначе бэк после редиректа переключится
// на Accept-Language (тот же приём, что в book-form/profile-edit).
$langQuery = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : null);

$withLang = static function (string $path) use ($langQuery): string {
    if ($langQuery === null || $langQuery === '') return $path;
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . urlencode($langQuery);
};

/** Значение поля: сначала то, что прислал пользователь, потом пусто. */
$val = static function (string $key, string $default = '') use ($form): string {
    if (array_key_exists($key, $form) && $form[$key] !== null && $form[$key] !== '') {
        return (string) $form[$key];
    }
    return $default;
};

/** Первая ошибка поля (сервер может прислать список). */
$err = static function (string $key) use ($errors): string {
    $list = $errors[$key] ?? null;
    if ($list === null) return '';
    $first = is_array($list) ? ($list[0] ?? null) : $list;
    return $first === null ? '' : (string) $first;
};

/** Все имена полей — для разбора $errors. */
$fieldKeys = ['title', 'description', 'genre', 'doi', 'type', 'content', 'book_id', 'page_start', 'page_end', 'cover'];

/** Ошибки, не привязанные к полю (показываем шапкой формы). */
$unboundErrors = [];
foreach ($errors as $field => $list) {
    if (in_array($field, $fieldKeys, true)) continue;
    foreach ((array) $list as $one) $unboundErrors[] = $one;
}

/** Поля с ошибками — JS покажет их сразу после загрузки (data-initial-errors). */
$dirtyFields = [];
foreach ($fieldKeys as $key) {
    if ($err($key) !== '') $dirtyFields[] = $key;
}

$typeValue = $val('type', 'content');
if (!in_array($typeValue, ['content', 'book'], true)) $typeValue = 'content';
?>

<?php $view->setBlock('selectedTab', 'articles'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.articleform.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/article-form.css') ?>">
  <script src="<?= $view->asset('js/article-form.js') ?>" defer></script>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.articleform.title'),
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

    <!-- TODO(роуты): action="<?= $view->e($withLang('/articles/store')) ?>"
         — роут articles.store добавит команда бэка, сейчас его нет,
         поэтому action="#". -->
    <form action="#"
          method="POST"
          id="articleForm"
          data-article-form
          data-initial-errors="<?= $view->e(implode(',', $dirtyFields)) ?>"
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>

      <h2 class="article-form__section-title"><?= $tr('common.articleform.section_main') ?></h2>

      <div class="form-field">
        <label for="articleTitle"><?= $tr('common.articleform.field_title') ?></label>
        <input class="form-field__input"
               type="text"
               id="articleTitle"
               name="title"
               value="<?= $view->e($val('title')) ?>"
               maxlength="255"
               autocomplete="off"
               required>
        <p class="form-field__error" data-error-for="title" aria-live="polite">
          <?php if ($err('title') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('title')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="form-field">
        <label for="articleAnnotation"><?= $tr('common.articleform.field_annotation') ?></label>
        <textarea class="form-field__input form-field__input--textarea"
                  id="articleAnnotation"
                  name="description"
                  rows="4"
                  maxlength="4000"
                  placeholder="<?= $tr('common.articleform.field_annotation_ph') ?>"><?= $view->e($val('description')) ?></textarea>
        <p class="form-field__error" data-error-for="description" aria-live="polite">
          <?php if ($err('description') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('description')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="article-form__row">

        <!-- Список жанров наполняет article-form.js из GET /api/additional/genres -->
        <div class="form-field">
          <label for="articleGenre"><?= $tr('common.articleform.field_genre') ?></label>
          <select class="form-field__input"
                  id="articleGenre"
                  name="genre"
                  data-dynamic-select="genres"
                  data-selected="<?= $view->e($val('genre')) ?>"
                  data-placeholder="<?= $tr('common.articleform.select_placeholder') ?>"
                  required>
            <?php if ($val('genre') !== ''): ?>
              <option value="<?= $view->e($val('genre')) ?>" selected><?= $view->e($val('genre')) ?></option>
            <?php else: ?>
              <option value="" selected disabled><?= $tr('common.articleform.select_placeholder') ?></option>
            <?php endif; ?>
          </select>
          <p class="form-field__error" data-error-for="genre" aria-live="polite">
            <?php if ($err('genre') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('genre')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

        <!-- DOI необязательный; подсказка — js.doi_hint (тот же текст,
             что в фильтре библиотеки) -->
        <div class="form-field">
          <label for="articleDoi"><?= $tr('common.articleform.field_doi') ?></label>
          <input class="form-field__input"
                 type="text"
                 id="articleDoi"
                 name="doi"
                 value="<?= $view->e($val('doi')) ?>"
                 maxlength="255"
                 autocomplete="off"
                 aria-describedby="articleDoiHint"
                 placeholder="10.5555/123456">
          <p class="article-form__hint" id="articleDoiHint"><?= $tr('common.js.doi_hint') ?></p>
          <p class="form-field__error" data-error-for="doi" aria-live="polite">
            <?php if ($err('doi') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('doi')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

      </div>

      <!-- ТИП: content — своя статья (нужен текст), book — отрывок из книги
           (нужны id книги и страницы). Разделы переключает article-form.js;
           без JS видны оба — проверит бэк. -->
      <fieldset class="article-form__type" data-article-type>
        <legend class="article-form__section-title"><?= $tr('common.articleform.field_type') ?></legend>

        <label class="article-form__radio">
          <input type="radio" name="type" value="content" <?= $typeValue === 'content' ? 'checked' : '' ?>>
          <span><?= $tr('common.articleform.type_standalone') ?></span>
        </label>
        <label class="article-form__radio">
          <input type="radio" name="type" value="book" <?= $typeValue === 'book' ? 'checked' : '' ?>>
          <span><?= $tr('common.articleform.type_excerpt') ?></span>
        </label>
      </fieldset>

      <div class="form-field" data-article-when="content">
        <label for="articleContent"><?= $tr('common.articleform.field_content') ?></label>
        <textarea class="form-field__input form-field__input--textarea"
                  id="articleContent"
                  name="content"
                  rows="12"
                  placeholder="<?= $tr('common.articleform.field_content_ph') ?>"><?= $view->e($val('content')) ?></textarea>
        <p class="form-field__error" data-error-for="content" aria-live="polite">
          <?php if ($err('content') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('content')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="article-form__row" data-article-when="book">
        <!-- TODO(api): автопоиск книги по названию — /api/books/search
             у бэка пока нет, поэтому пока обычное поле с id. -->
        <div class="form-field">
          <label for="articleBookId"><?= $tr('common.articleform.field_book') ?></label>
          <input class="form-field__input"
                 type="number"
                 id="articleBookId"
                 name="book_id"
                 min="1"
                 value="<?= $view->e($val('book_id')) ?>"
                 placeholder="<?= $tr('common.articleform.field_book_ph') ?>">
          <p class="form-field__error" data-error-for="book_id" aria-live="polite">
            <?php if ($err('book_id') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('book_id')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

        <div class="form-field">
          <label for="articlePageStart"><?= $tr('common.articleform.field_page_from') ?></label>
          <input class="form-field__input"
                 type="number"
                 id="articlePageStart"
                 name="page_start"
                 min="1"
                 value="<?= $view->e($val('page_start')) ?>">
          <p class="form-field__error" data-error-for="page_start" aria-live="polite">
            <?php if ($err('page_start') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('page_start')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

        <div class="form-field">
          <label for="articlePageEnd"><?= $tr('common.articleform.field_page_to') ?></label>
          <input class="form-field__input"
                 type="number"
                 id="articlePageEnd"
                 name="page_end"
                 min="1"
                 value="<?= $view->e($val('page_end')) ?>">
          <p class="form-field__error" data-error-for="page_end" aria-live="polite">
            <?php if ($err('page_end') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('page_end')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <h2 class="article-form__section-title"><?= $tr('common.articleform.field_cover') ?></h2>

      <!-- ОБЛОЖКА: превью показывает article-form.js (object URL),
           без JS ничего не ломается — файл всё равно уйдёт в POST. -->
      <div class="form-field">
        <div class="cover-upload" data-cover-upload>
          <div class="cover-upload__frame">
            <img class="cover-upload__preview"
                 data-cover-preview
                 alt="<?= $tr('common.articleform.cover_preview_alt') ?>"
                 hidden>
            <span class="cover-upload__placeholder" data-cover-placeholder><?= $tr('common.articleform.cover_placeholder') ?></span>
          </div>

          <div class="cover-upload__controls">
            <input class="file-input-hidden"
                   type="file"
                   id="articleCover"
                   name="cover"
                   accept="image/jpeg,image/png,image/webp"
                   data-cover-input>
            <label class="btn btn--secondary file-upload__button" for="articleCover">
              <span data-file-button-text><?= $tr('common.articleform.file_choose') ?></span>
              <span class="file-upload__button-state" data-file-button-state><?= $tr('common.articleform.file_none') ?></span>
            </label>
            <p class="article-form__hint"><?= $tr('common.articleform.cover_hint') ?></p>
            <button class="btn btn--secondary"
                    type="button"
                    data-cover-remove
                    hidden><?= $tr('common.articleform.cover_remove') ?></button>
          </div>
        </div>

        <p class="form-field__error" data-error-for="cover" aria-live="polite">
          <?php if ($err('cover') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('cover')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="article-form__actions">
        <a class="btn btn--secondary" href="<?= $view->e($withLang($view->url('articles'))) ?>"><?= $tr('common.articleform.cancel') ?></a>
        <button class="btn btn--primary" type="submit"><?= $tr('common.articleform.submit') ?></button>
      </div>
    </form>

  </section>

</div>

<?php $view->endBlock('content'); ?>
