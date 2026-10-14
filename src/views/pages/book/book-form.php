<?php $view->extends('main'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
// Сохраняем локаль в action и в скрытом поле — иначе бэк после редиректа
// переключится на Accept-Language и вернёт другую локаль (как в profile-edit).
$langQuery = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : (method_exists($view, 'locale') ? $view->locale() : null);

$withLang = static function (string $path) use ($langQuery): string {
    if ($langQuery === null || $langQuery === '') return $path;
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . urlencode($langQuery);
};

/** Скрытое поле формы: гарантирует, что POST унесёт lang на бэк. */
$langField = $langQuery !== null && $langQuery !== ''
    ? '<input type="hidden" name="lang" value="' . $view->e($langQuery) . '">'
    : '';

/*
 * КОНТРАКТ С БЭКОМ (P0, фронт готов — роуты добавляет команда бэка;
 * config/routes.php и app/ ЗАМОРОЖЕНЫ, правки запрещены):
 *
 *   GET  /books/create  → роут books.create,        AuthPolicy::Verified
 *        рендерит эту вьюху, передавая $user (для сайдбара);
 *        при повторном рендере после ошибок — $form (значения полей) и
 *        $errors (['title' => ['msg'], ...], ключи 'cover', 'files',
 *        'authors', 'editors').
 *
 *   POST /books/create  → роут books.create.submit, AuthPolicy::Verified
 *        enctype=multipart/form-data, поля:
 *          title (string, required, ≤200)
 *          annotation (string, optional, ≤5000)
 *          genre (int id, required)     — список: GET /api/additional/genres
 *          category (int id, required)  — список: GET /api/additional/categories
 *          series (string, optional, ≤120) — в БД поля пока нет (см. book-details)
 *          isbn (string, optional, ≤20) — форма хранит до 20 символов, фронт
 *                  проверяет ISBN-13 (13 цифр, префикс 978/979, дефисы и
 *                  пробелы допустимы); бэк — та же проверка (см. фильтр
 *                  библиотеки: filters.js isbnValidate)
 *          authors[]  (array<int id>, optional, ≤30) — авторы книги;
 *                  ПОЛЕ СОЗДАТЕЛЯ НЕТ: создатель всегда один, не изменяем
 *                  и автоматически редактор (берётся из сессии бэком).
 *                  Гидрация после 422: $form['authors'] = [['id'=>..,
 *                  'username'=>..], ...]
 *          editors[]  (array<int id>, optional, без лимита) — редакторы;
 *                  в `$form` — та же форма, что у authors[]
 *          cover (file: jpeg/png/webp, ≤5MB, optional — иначе placeholder)
 *          files[] (array<file>, required ≥1; форматы pdf/epub/mobi/fb2,
 *                  ≤50MB на файл) — книга в одном или нескольких форматах
 *          _token (CSRF: поле csrfField() или заголовок X-CSRF-Token), lang
 *        успех: Accept: application/json → 201 { data: { id, redirect: '/books/{id}' } }
 *               без JSON (прогрессивный POST без JS) → 302 на /books/{id}
 *        ошибки валидации → 422 { errors: { field: [msg, ...] } }
 *
 *   GET  /api/users/search?q=… → роут api.users.search, AuthPolicy::Auth
 *        ЗАГЛУШКА (роут добавит бэк; в endpoints.php его пока нет):
 *        автокомплит людей по нику для списков авторов/редакторов.
 *        Ответ: { items: [ { id: int, username: string }, ... ] }
 *        (формат ResponseTemplates::list). Фронт при ошибке/404 молча
 *        показывает «поиск недоступен», форма не ломается.
 *
 *   Сохранение файлов — по образцу App\Lib\Uploader::saveImage (cover) и
 *   отдельный приём книги (is_uploaded_file + finfo, формат — по
 *   расширению/MIME из списка pdf/epub/mobi/fb2, имя генерируется).
 */
$form   = $form   ?? [];
$errors = $errors ?? [];

/** Значение поля: сначала то, что прислал пользователь, потом пусто. */
$val = static function (string $key, string $default = '') use ($form): string {
    if (array_key_exists($key, $form) && $form[$key] !== null && $form[$key] !== '') {
        return (string) $form[$key];
    }
    return $default;
};

/**
 * Гидрация списков людей после 422: $form['authors'/'editors'] — список
 * ['id' => int|string, 'username' => string]. Пусто, если поле не пришло.
 */
$people = static function (string $key) use ($form): array {
    $raw = $form[$key] ?? null;
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $one) {
        if (is_array($one)) {
            $id = $one['id'] ?? null;
            $username = (string) ($one['username'] ?? '');
        } else {
            $id = $one;
            $username = (string) $one;
        }
        if ($id === null || $id === '') continue;
        $out[] = ['id' => (string) $id, 'username' => $username !== '' ? $username : (string) $id];
    }
    return $out;
};

$authors = $people('authors');
$editors = $people('editors');

/** Первая ошибка поля (сервер может прислать список). */
$err = static function (string $key) use ($errors): string {
    $list = $errors[$key] ?? null;
    if ($list === null) return '';
    $first = is_array($list) ? ($list[0] ?? null) : $list;
    return $first === null ? '' : (string) $first;
};

/** Все имена полей формы — для разбора $errors. */
$fieldKeys = ['title', 'annotation', 'genre', 'category', 'series', 'isbn', 'cover', 'files', 'authors', 'editors'];

/** Ошибки, не привязанные к полю (например, «файл не сохранён»). */
$unboundErrors = [];
foreach ($errors as $field => $list) {
    if (in_array($field, $fieldKeys, true)) continue;
    foreach ((array) $list as $one) $unboundErrors[] = $one;
}

// Ошибки, которые JS ещё не разобрал (уведомление при загрузке, как в profile-edit).
$dirtyFields = [];
foreach ($fieldKeys as $key) {
    if ($err($key) !== '') $dirtyFields[] = $key;
}

$genreValue    = $val('genre');
$categoryValue = $val('category');
?>

<?php $view->startBlock('title'); ?><?= $tr('common.bookform.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/book-form.css') ?>">
  <script src="<?= $view->asset('js/book-form.js') ?>" defer></script>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.bookform.title'),
    'subtitle' => $view->t('common.bookform.subtitle'),
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

<div class="book-form-grid">

  <!-- ============================================================
       FORM: Create book (метаданные + обложка + файлы в разных форматах)
       ============================================================ -->
  <section class="book-form card-base">

    <form action="<?= $view->e($withLang('/books/create')) ?>"
          method="POST"
          id="bookForm"
          data-book-form
          data-initial-errors="<?= $view->e(implode(',', $dirtyFields)) ?>"
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>
      <?= $langField ?>

      <h2 class="book-form__section-title"><?= $tr('common.bookform.section_main') ?></h2>

      <div class="form-field">
        <label for="bookTitle"><?= $tr('common.bookform.field_title') ?></label>
        <input class="form-field__input"
               type="text"
               id="bookTitle"
               name="title"
               value="<?= $view->e($val('title')) ?>"
               maxlength="200"
               autocomplete="off"
               required>
        <p class="form-field__error" data-error-for="title" aria-live="polite">
          <?php if ($err('title') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('title')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <!-- АВТОРЫ: поиск людей по нику (заглушка /api/users/search),
           список до 30 человек под поиском, у каждого кнопка
           «сделать редактором» (переносит его же в список редакторов,
           остаётся автором) и минус для удаления. -->
      <div class="form-field" data-people-field="authors">
        <label for="bookAuthorsSearch"><?= $tr('common.bookform.field_authors') ?></label>
        <input class="form-field__input"
               type="text"
               id="bookAuthorsSearch"
               data-people-search
               maxlength="100"
               autocomplete="off"
               placeholder="<?= $tr('common.bookform.people_search_ph') ?>"
               aria-describedby="bookAuthorsHint">
        <ul class="people-search__results" data-people-results hidden></ul>
        <ul class="people-list" data-people-list>
          <?php foreach ($authors as $person): ?>
            <li class="people-list__item" data-person-id="<?= $view->e($person['id']) ?>" data-person-name="<?= $view->e($person['username']) ?>">
              <span class="people-list__name"><?= $view->e($person['username']) ?></span>
              <button class="people-list__action" type="button" data-make-editor><?= $tr('common.bookform.make_editor') ?></button>
              <button class="people-list__remove" type="button" data-remove-person aria-label="<?= $tr('common.bookform.remove_person') ?>">−</button>
            </li>
          <?php endforeach; ?>
        </ul>
        <!-- по одному hidden на человека: без JS POST унесёт authors[]=id,… -->
        <?php foreach ($authors as $person): ?>
          <input type="hidden" name="authors[]" data-people-value value="<?= $view->e($person['id']) ?>">
        <?php endforeach; ?>
        <p class="book-form__hint" id="bookAuthorsHint"><?= $tr('common.bookform.authors_hint') ?></p>
        <p class="form-field__error" data-error-for="authors" aria-live="polite">
          <?php if ($err('authors') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('authors')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <!-- РЕДАКТОРЫ: тот же поиск, лимита нет; минус убирает из списка.
           Создатель книги сюда не попадает — он редактор по умолчанию. -->
      <div class="form-field" data-people-field="editors">
        <label for="bookEditorsSearch"><?= $tr('common.bookform.field_editors') ?></label>
        <input class="form-field__input"
               type="text"
               id="bookEditorsSearch"
               data-people-search
               maxlength="100"
               autocomplete="off"
               placeholder="<?= $tr('common.bookform.people_search_ph') ?>"
               aria-describedby="bookEditorsHint">
        <ul class="people-search__results" data-people-results hidden></ul>
        <ul class="people-list" data-people-list>
          <?php foreach ($editors as $person): ?>
            <li class="people-list__item" data-person-id="<?= $view->e($person['id']) ?>" data-person-name="<?= $view->e($person['username']) ?>">
              <span class="people-list__name"><?= $view->e($person['username']) ?></span>
              <button class="people-list__remove" type="button" data-remove-person aria-label="<?= $tr('common.bookform.remove_person') ?>">−</button>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php foreach ($editors as $person): ?>
          <input type="hidden" name="editors[]" data-people-value value="<?= $view->e($person['id']) ?>">
        <?php endforeach; ?>
        <p class="book-form__hint" id="bookEditorsHint"><?= $tr('common.bookform.editors_hint') ?></p>
        <p class="form-field__error" data-error-for="editors" aria-live="polite">
          <?php if ($err('editors') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('editors')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="form-field">
        <label for="bookAnnotation"><?= $tr('common.bookform.field_annotation') ?></label>
        <textarea class="form-field__input form-field__input--textarea"
                  id="bookAnnotation"
                  name="annotation"
                  rows="6"
                  maxlength="5000"
                  placeholder="<?= $tr('common.bookform.field_annotation_ph') ?>"><?= $view->e($val('annotation')) ?></textarea>
        <p class="form-field__error" data-error-for="annotation" aria-live="polite">
          <?php if ($err('annotation') !== ''): ?>
            <span class="field-error-icon" title="<?= $view->e($err('annotation')) ?>">!</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="form-row--two-cols">

        <div class="form-field">
          <label for="bookGenre"><?= $tr('common.bookform.field_genre') ?></label>
          <!-- Список наполняет book-form.js из GET /api/additional/genres.
               data-selected — id из $form (после ошибки валидации). -->
          <select class="form-field__input book-form__select"
                  id="bookGenre"
                  name="genre"
                  data-dynamic-select="genres"
                  data-selected="<?= $view->e($genreValue) ?>"
                  data-placeholder="<?= $tr('common.bookform.select_placeholder') ?>"
                  required>
            <?php if ($genreValue !== ''): ?>
              <option value="<?= $view->e($genreValue) ?>" selected><?= $view->e($genreValue) ?></option>
            <?php else: ?>
              <option value="" selected disabled><?= $tr('common.bookform.select_placeholder') ?></option>
            <?php endif; ?>
          </select>
          <p class="form-field__error" data-error-for="genre" aria-live="polite">
            <?php if ($err('genre') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('genre')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

        <div class="form-field">
          <label for="bookCategory"><?= $tr('common.bookform.field_category') ?></label>
          <!-- Список: GET /api/additional/categories -->
          <select class="form-field__input book-form__select"
                  id="bookCategory"
                  name="category"
                  data-dynamic-select="categories"
                  data-selected="<?= $view->e($categoryValue) ?>"
                  data-placeholder="<?= $tr('common.bookform.select_placeholder') ?>"
                  required>
            <?php if ($categoryValue !== ''): ?>
              <option value="<?= $view->e($categoryValue) ?>" selected><?= $view->e($categoryValue) ?></option>
            <?php else: ?>
              <option value="" selected disabled><?= $tr('common.bookform.select_placeholder') ?></option>
            <?php endif; ?>
          </select>
          <p class="form-field__error" data-error-for="category" aria-live="polite">
            <?php if ($err('category') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('category')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

      </div>

      <div class="form-row--two-cols">

        <div class="form-field">
          <label for="bookSeries"><?= $tr('common.bookform.field_series') ?></label>
          <input class="form-field__input"
                 type="text"
                 id="bookSeries"
                 name="series"
                 value="<?= $view->e($val('series')) ?>"
                 maxlength="120"
                 autocomplete="off"
                 placeholder="<?= $tr('common.bookform.field_series_ph') ?>">
          <p class="form-field__error" data-error-for="series" aria-live="polite">
            <?php if ($err('series') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('series')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

        <!-- ISBN необязательный; форма и JS проверяют ISBN-13 (13 цифр,
             префикс 978/979, дефисы/пробелы допустимы) — логика зеркалит
             isbnValidate из filters.js. Подсказка — js.isbn_hint. -->
        <div class="form-field">
          <label for="bookIsbn"><?= $tr('common.bookform.field_isbn') ?></label>
          <input class="form-field__input"
                 type="text"
                 id="bookIsbn"
                 name="isbn"
                 value="<?= $view->e($val('isbn')) ?>"
                 maxlength="20"
                 inputmode="numeric"
                 autocomplete="off"
                 aria-describedby="bookIsbnHint"
                 placeholder="<?= $tr('common.bookform.isbn_ph') ?>">
          <p class="book-form__hint" id="bookIsbnHint"><?= $tr('common.js.isbn_hint') ?></p>
          <p class="form-field__error" data-error-for="isbn" aria-live="polite">
            <?php if ($err('isbn') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('isbn')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

      </div>

      <h2 class="book-form__section-title"><?= $tr('common.bookform.section_files') ?></h2>

      <div class="form-row--files">

        <!-- ОБЛОЖКА: превью показывает book-form.js (object URL), без JS —
             ничего не ломается, файл всё равно уйдёт в POST. -->
        <div class="form-field">
          <label for="bookCover"><?= $tr('common.bookform.field_cover') ?></label>

          <div class="cover-upload" data-cover-upload>
            <div class="cover-upload__frame">
              <img class="cover-upload__preview"
                   data-cover-preview
                   alt="<?= $tr('common.bookform.cover_preview_alt') ?>"
                   hidden>
              <span class="cover-upload__placeholder" data-cover-placeholder><?= $tr('common.bookform.cover_placeholder') ?></span>
            </div>

            <div class="cover-upload__controls">
              <input class="form-field__input form-field__input--file"
                     type="file"
                     id="bookCover"
                     name="cover"
                     accept="image/jpeg,image/png,image/webp"
                     data-cover-input>
              <p class="book-form__hint"><?= $tr('common.bookform.cover_hint') ?></p>
              <button class="btn btn--secondary"
                      type="button"
                      data-cover-remove
                      hidden><?= $tr('common.bookform.cover_remove') ?></button>
            </div>
          </div>

          <p class="form-field__error" data-error-for="cover" aria-live="polite">
            <?php if ($err('cover') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('cover')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

        <!-- ФАЙЛЫ: один input multiple на pdf/epub/mobi/fb2, минимум один
             файл. Список выбранных (имя, размер, минус) рисует book-form.js.
             Без JS уходит обычным POST — several files[] в multipart. -->
        <div class="form-field" data-files-field>
          <label for="bookFiles"><?= $tr('common.bookform.field_file') ?></label>

          <div class="file-upload" data-file-upload>
            <input class="form-field__input form-field__input--file"
                   type="file"
                   id="bookFiles"
                   name="files[]"
                   accept=".pdf,.epub,.mobi,.fb2,application/pdf,application/epub+zip,application/x-mobipocket-ebook,application/fb2"
                   multiple
                   data-files-input
                   required>

            <ul class="file-upload__list" data-files-list></ul>

            <p class="book-form__hint"><?= $tr('common.bookform.file_hint') ?></p>
          </div>

          <p class="form-field__error" data-error-for="files" aria-live="polite">
            <?php if ($err('files') !== ''): ?>
              <span class="field-error-icon" title="<?= $view->e($err('files')) ?>">!</span>
            <?php endif; ?>
          </p>
        </div>

      </div>

      <div class="book-form__actions">
        <a class="btn btn--secondary" href="<?= $view->e($withLang($view->url('books'))) ?>"><?= $tr('common.bookform.cancel') ?></a>
        <button class="btn btn--primary" type="submit"><?= $tr('common.bookform.submit') ?></button>
      </div>
    </form>

  </section>

</div>

<?php $view->endBlock('content'); ?>
