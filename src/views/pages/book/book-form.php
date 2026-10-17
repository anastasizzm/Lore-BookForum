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
 *   POST /books/store   → роут books.store, AuthPolicy::Verified
 *        (P0: имя роута books.store; ветка фронта, бэк добавит роут —
 *        сейчас POST /books/store отвечает 405, см. TESTING.md)
 *        enctype=multipart/form-data, поля:
 *          title (string, required, ≤200)
 *          annotation (string, optional, ≤5000)
 *          author_notes (string, optional, ≤2000) — «заметки автора»,
 *                  колонка author_notes уже есть в БД (PublicationExtended);
 *                  если бэк назовёт поле по-статьейному — author_note —
 *                  переименовать здесь (алиас authorNotes).
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
 *                  не более одного файла каждого формата, ≤50MB на файл) —
 *                  книга в одном или нескольких форматах; дубли форматов
 *                  бэк тоже должен отсекать (фронт уже отсекает)
 *          status ('draft' | 'published', required) — кнопка-сабмитер
 *                  Нижней панели шага 2 (Create draft / Publish):
 *                    draft     → книга-черновик: видит только создатель
 *                                (политику держит бэк), успех → фронт
 *                                уводит на book-details (бейдж «Черновик»,
 *                                кнопка публикации внизу);
 *                    published → после УСПЕХА проверки фронт открывает
 *                                плашку выбора прав (см. ниже) и лишь
 *                                потом ведёт на страницу книги.
 *          _token (CSRF: поле csrfField() или заголовок X-CSRF-Token), lang
 *        успех: Accept: application/json → 201 { data: { id, redirect } }
 *               redirect — редирект бэка; если его нет, фронт уводит
 *               пользователя на страницу книги /books/{id} (draft) или
 *               на неё же после подтверждения прав (published).
 *               без JSON (прогрессивный POST без JS) → 302 на /books
 *        ошибки валидации → 422 { errors: { field: [msg, ...] } }
 *               Фронт возвращает пользователя на шаг, где ошибка: при
 *               ошибках сразу в двух шагах — сначала первый (порядок
 *               полей в DOM), дальше пользователь идёт сам.
 *
 *   РЕЖИМ РЕДАКТИРОВАНИЯ (по образцу article-form):
 *   GET  /books/{id}/edit → роут books.edit, AuthPolicy::Verified (бэк)
 *        рендерит ЭТУ ЖЕ вьюху, передавая $book — гидрированную модель
 *        Book: id, title, genre(id), category(id), series, isbn,
 *        annotation (description), authorNotes, authors/editors, status.
 *        Вьюша прелфиллит поля из $book; $form (после 422) перебивает.
 *        Сохранение — тот же POST /books/store со скрытым полем id:
 *          id пусто → создание (201), id > 0 → обновление
 *          (200, redirect /books/{id}). В edit-режиме кнопка «Сохранить»
 *          шлёт status = ТЕКУЩИЙ статус книги (не меняет его).
 *        Нижняя панель edit-режима: ← Назад | Отмена | Сохранить |
 *        Опубликовать (ВСЕГДА; data-rights-open — открывает плашку выбора
 *        прав, rights-modal.js mode='publish': POST rights[] +
 *        POST /books/{id}/publish → переход на страницу книги).
 *        Локальная копия (localStorage «Восстановить») в edit-режиме
 *        НЕ пишется — только для создания.
 *
 *   ПУБЛИКАЦИЯ И ПРАВА (плашка rights-modal.php, JS rights-modal.js):
 *   GET  /api/additional/rights         — справочник прав, АСИНХРОННО при
 *        открытии плашки: 200 { items: [ { id, title, description? } ] }
 *        (по аналогии с /api/additional/genres, ResponseTemplates::list)
 *   POST /api/publications/{id}/rights  — сохранение выбранных прав:
 *        поля rights[] (array<int>), CSRF; 200/204 ок, 422 с errors
 *   POST /books/{id}/publish            — черновик → published; используется
 *        плашкой на book-details (кнопка «Опубликовать книгу»), успех →
 *        перезагрузка страницы (бейдж и кнопка исчезают)
 *
 *   ГЛАВЫ КНИГИ: UI-список есть (шаг 2), но input'ы БЕЗ name — в POST не
 *   уходят: контракт (одна глава = номер + название? + текст?) ещё не
 *   согласован. Подключить, когда бэк определит форму данных.
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
 *
 * ---------------------------------------------------------------
 * МНОГОШАГОВОСТЬ (FORM-01, UI — по образцу article-form.php):
 *
 *   Шаг 1 «Данные книги» — формальности: название, жанр, категория,
 *                          серия, ISBN, обложка.
 *   Шаг 2 «Содержание и файлы» — аннотация, заметки автора, главы,
 *                          файлы книги; временно здесь же списки
 *                          авторов/редакторов и кнопки отправки.
 *
 *   ШАГ 3 «РОЛИ» (права, авторы/редакторы, финальные кнопки
 *   «Сохранить черновик» / «Опубликовать» → book-details → подтверждение)
 *   СОЗДАВАТЬСЯ НЕ БУДЕТ — пропущен по решению команды, доделаем отдельно:
 *   тогда списки людей и блок .book-step__nav с кнопками переедут туда
 *   (разметка уже готова — нужен только перенос).
 *
 *   Переход «Далее» прогоняет валидацию полей своего шага
 *   (см. book-form.js, STEP_FIELDS): без валидных обязательных полей
 *   дальше не пускает.
 *
 *   НЕСОХРАНЁННЫЕ ИЗМЕНЕНИЯ: книга пишется в localStorage
 *   (ключ bookform:v1:new) при вводе; при следующем открытии
 *   /books/create на шаге 1 показывается плашка «Восстановить /
 *   Отбросить» (data-restore). Копия стирается после успешной отправки.
 */
$form   = $form   ?? [];
$errors = $errors ?? [];

/**
 * Режим редактирования: контроллер /books/{id}/edit передаёт $book.
 * Тест-роут /pages/test отдаёт query-массивами — читаем поля и через
 * объект, и через массив.
 */
$book = $book ?? null;
$get  = static function ($src, string $key, $default = null) {
    if (is_array($src)) return array_key_exists($key, $src) ? $src[$key] : $default;
    if (is_object($src)) return $src->$key ?? $default;
    return $default;
};

$bookId     = (int) $get($book, 'id', 0);
$isEdit     = $book !== null && $bookId > 0;
$bookStatus = strtolower(trim((string) ($get($book, 'status', '') ?: 'draft')));

/** Дефолт поля из книги (edit-режим); $form (после 422) всегда перебивает. */
$fromBook = static function (string $key, string $default = '') use ($get, $book): string {
    if ($book === null) return $default;
    $raw = $get($book, $key, null);
    if (is_array($raw) || $raw === null || $raw === '') return $default;
    if (is_object($raw)) $raw = (string) $raw;
    return $raw === '' ? $default : (string) $raw;
};

// Жанр/категория в модели — объекты; для селекта нужен id.
$bookGenreId    = (string) ($get($get($book, 'genre', null), 'id', '') ?: $get($book, 'genre_id', ''));
$bookCategoryId = (string) ($get($get($book, 'category', null), 'id', '') ?: $get($book, 'category_id', ''));

/** Значение поля: сначала то, что прислал пользователь, потом книга (edit). */
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

// edit-режим: списки людей из $book, если контроллер их прислал
$bookPeople = static function (string $key) use ($get, $book): array {
    $raw = $get($book, $key, null);
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $one) {
        $id = $get($one, 'id', null);
        if ($id === null || $id === '') continue;
        $out[] = ['id' => (string) $id, 'username' => (string) ($get($one, 'username', '') ?: $id)];
    }
    return $out;
};
if ($authors === []) $authors = $bookPeople('authors');
if ($editors === []) $editors = $bookPeople('editors');

/** Первая ошибка поля (сервер может прислать список). */
$err = static function (string $key) use ($errors): string {
    $list = $errors[$key] ?? null;
    if ($list === null) return '';
    $first = is_array($list) ? ($list[0] ?? null) : $list;
    return $first === null ? '' : (string) $first;
};

/**
 * Ошибка поля + алиасы (бэк может прислать authorNotes вместо author_notes).
 */
$errOf = static function (string $key) use ($err): string {
    $first = $err($key);
    if ($first !== '') return $first;
    $aliases = ['author_notes' => ['author_note', 'authorNotes']];
    foreach ($aliases[$key] ?? [] as $alias) {
        $one = $err($alias);
        if ($one !== '') return $one;
    }
    return '';
};

/** <p> со слотом ошибки для JS ([data-error-for]) + серверный текст. */
$errorBox = static function (string $key) use ($view, $errOf): string {
    $msg = $errOf($key);
    return '<p class="form-field__error" data-error-for="' . $view->e($key) . '" aria-live="polite">'
        . ($msg !== '' ? '<span class="field-error-icon" title="' . $view->e($msg) . '">!</span>' : '')
        . '</p>';
};

/** Все имена полей формы — для разбора $errors. */
$fieldKeys = ['title', 'annotation', 'author_notes', 'genre', 'category', 'series', 'isbn',
              'cover', 'files', 'authors', 'editors'];

/** Ошибки, не привязанные к полю (например, «файл не сохранён»). */
$unboundErrors = [];
foreach ($errors as $field => $list) {
    if (in_array($field, $fieldKeys, true)) continue;
    if (in_array($field, ['author_note', 'authorNotes'], true)) continue;
    foreach ((array) $list as $one) $unboundErrors[] = $one;
}

// Ошибки, которые JS ещё не разобрал (уведомление при загрузке, как в profile-edit).
$dirtyFields = [];
foreach ($fieldKeys as $key) {
    if ($errOf($key) !== '') $dirtyFields[] = $key;
}

$genreValue    = $val('genre', $bookGenreId);
$categoryValue = $val('category', $bookCategoryId);
?>

<?php $view->startBlock('title'); ?><?= $tr($isEdit ? 'common.bookform.title_edit' : 'common.bookform.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/book-form.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/rights-modal.css') ?>">
  <!-- rights-modal.js — ДО book-form.js: после успешной публикации форма
       вызывает window.RightsModal.open(...) -->
  <script src="<?= $view->asset('js/rights-modal.js') ?>" defer></script>
  <script src="<?= $view->asset('js/book-form.js') ?>" defer></script>
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t($isEdit ? 'common.bookform.title_edit' : 'common.bookform.title'),
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
       FORM: Create book — многошаговая (FORM-01)
       ============================================================ -->
  <section class="book-form card-base">

    <!-- P0: action = books.store (POST /books/store). Роут books.store
         добавит команда бэка (config/ и app/ для фронта заморожены),
         поэтому путь захардкожен, а не через $view->url('books.store'),
         который бросил бы RuntimeException при неизвестном имени. -->
    <form action="<?= $view->e($withLang('/books/store')) ?>"
          method="POST"
          id="bookForm"
          data-book-form
          data-initial-errors="<?= $view->e(implode(',', $dirtyFields)) ?>"
          <?php if ($isEdit): ?>data-book-id="<?= $bookId ?>"
                   data-book-status="<?= $view->e($bookStatus) ?>"<?php endif; ?>
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>
      <?= $langField ?>
      <?php if ($isEdit): ?>
        <!-- edit-режим: id > 0 → POST /books/store обновляет книгу (контракт в шапке) -->
        <input type="hidden" name="id" value="<?= $bookId ?>">
      <?php endif; ?>

      <!-- ============================================================
           ШАГ 1. ОСНОВНЫЕ ДАННЫЕ (формальности)
           ============================================================ -->
      <section class="book-step" data-step="1">
        <header class="book-step__header">
          <!-- Когда появится шаг 3 «Роли» — заменить на 1 / 3 -->
          <span class="book-step__num">1 / 2</span>
          <h2 class="book-step__title"><?= $tr('common.bookform.step1_title') ?></h2>
        </header>

        <!-- Найдена локальная копия несохранённых изменений (см. book-form.js).
             В edit-режиме не показывается — локальная копия пишется только
             при создании (ключ bookform:v1:new). -->
        <?php if (!$isEdit): ?>
        <div class="book-form__restore" data-restore role="status" hidden>
          <p class="book-form__restore-text"><?= $tr('common.bookform.restore_text') ?></p>
          <div class="book-form__restore-actions">
            <button type="button" class="btn btn--primary" data-restore-apply><?= $tr('common.bookform.restore_apply') ?></button>
            <button type="button" class="btn btn--secondary" data-restore-discard><?= $tr('common.bookform.restore_discard') ?></button>
          </div>
        </div>
        <?php endif; ?>

        <div class="form-field">
          <label for="bookTitle"><?= $tr('common.bookform.field_title') ?></label>
          <input class="form-field__input"
                 type="text"
                 id="bookTitle"
                 name="title"
                 value="<?= $view->e($val('title', $fromBook('title'))) ?>"
                 maxlength="200"
                 autocomplete="off"
                 required>
          <?= $errorBox('title') ?>
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
            <?= $errorBox('genre') ?>
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
            <?= $errorBox('category') ?>
          </div>

        </div>

        <div class="form-row--two-cols">

          <div class="form-field">
            <label for="bookSeries"><?= $tr('common.bookform.field_series') ?></label>
            <input class="form-field__input"
                   type="text"
                   id="bookSeries"
                   name="series"
                   value="<?= $view->e($val('series', $fromBook('series'))) ?>"
                   maxlength="120"
                   autocomplete="off"
                   placeholder="<?= $tr('common.bookform.field_series_ph') ?>">
            <?= $errorBox('series') ?>
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
                   value="<?= $view->e($val('isbn', $fromBook('isbn'))) ?>"
                   maxlength="20"
                   inputmode="numeric"
                   autocomplete="off"
                   aria-describedby="bookIsbnHint"
                   placeholder="<?= $tr('common.bookform.isbn_ph') ?>">
            <p class="book-form__hint" id="bookIsbnHint"><?= $tr('common.js.isbn_hint') ?></p>
            <?= $errorBox('isbn') ?>
          </div>

        </div>

        <!-- ОБЛОЖКА: превью показывает book-form.js (object URL), без JS —
             ничего не ломается, файл всё равно уйдёт в POST. -->
        <div class="form-field book-form__cover">
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
              <!-- Нативная строка браузера («Choose file») не переводится:
                   input спрятан, клик ведёт локализованная кнопка-лейбл
                   (Выбрать файл… / Файл не выбран). -->
              <input class="file-input-hidden"
                     type="file"
                     id="bookCover"
                     name="cover"
                     accept="image/jpeg,image/png,image/webp"
                     data-cover-input>
              <label class="btn btn--secondary file-upload__button" for="bookCover">
                <span data-file-button-text><?= $tr('common.bookform.file_choose') ?></span>
                <span class="file-upload__button-state" data-file-button-state><?= $tr('common.bookform.file_none') ?></span>
              </label>
              <p class="book-form__hint"><?= $tr('common.bookform.cover_hint') ?></p>
              <button class="btn btn--secondary"
                      type="button"
                      data-cover-remove
                      hidden><?= $tr('common.bookform.cover_remove') ?></button>
            </div>
          </div>

          <?= $errorBox('cover') ?>
        </div>

        <div class="book-step__nav">
          <button type="button" class="btn btn--primary" data-step-next>
            <?= $tr('common.bookform.next') ?> →
          </button>
        </div>
      </section>

      <!-- ============================================================
           ШАГ 2. СОДЕРЖАНИЕ И ФАЙЛЫ
           ============================================================ -->
      <section class="book-step" data-step="2" hidden>
        <header class="book-step__header">
          <!-- Когда появится шаг 3 «Роли» — заменить на 2 / 3 -->
          <span class="book-step__num">2 / 2</span>
          <h2 class="book-step__title"><?= $tr('common.bookform.step2_title') ?></h2>
        </header>

        <h3 class="book-form__section-title"><?= $tr('common.bookform.section_content') ?></h3>

        <div class="form-field">
          <label for="bookAnnotation"><?= $tr('common.bookform.field_annotation') ?></label>
          <textarea class="form-field__input form-field__input--textarea"
                    id="bookAnnotation"
                    name="annotation"
                    rows="6"
                    maxlength="5000"
                    placeholder="<?= $tr('common.bookform.field_annotation_ph') ?>"><?= $view->e($val('annotation', $fromBook('description'))) ?></textarea>
          <?= $errorBox('annotation') ?>
        </div>

        <!-- Заметки автора: колонка author_notes уже есть в БД
             (PublicationExtended), поле уходит на /books/store. -->
        <div class="form-field">
          <label for="bookAuthorNotes"><?= $tr('common.bookform.field_author_note') ?></label>
          <textarea class="form-field__input form-field__input--textarea"
                    id="bookAuthorNotes"
                    name="author_notes"
                    rows="4"
                    maxlength="2000"
                    placeholder="<?= $tr('common.bookform.author_note_ph') ?>"><?= $view->e($val('author_notes', $fromBook('authorNotes'))) ?></textarea>
          <?= $errorBox('author_notes') ?>
        </div>

        <!-- ГЛАВЫ: только UI — input'ы БЕЗ name, в POST не участвуют
             (контракт с бэкендом уточняется, см. шапку файла).
             Значения попадают в локальную копию несохранённых изменений,
             поэтому восстанавливаются плашкой на шаге 1. -->
        <div class="form-field" data-chapters-field>
          <span class="form-field__label" id="bookChaptersLabel"><?= $tr('common.bookform.chapters_label') ?></span>

          <ul class="chapters" data-chapters aria-labelledby="bookChaptersLabel">
            <?php foreach ((array) ($form['chapters'] ?? []) as $chapterTitle): ?>
              <li class="chapters__item" data-chapter>
                <span class="chapters__num" data-chapter-num aria-hidden="true"></span>
                <input class="form-field__input chapters__input"
                       type="text"
                       data-chapter-title
                       maxlength="200"
                       autocomplete="off"
                       placeholder="<?= $tr('common.bookform.chapter_ph') ?>"
                       value="<?= $view->e((string) $chapterTitle) ?>">
                <button class="chapters__remove" type="button" data-chapter-remove
                        aria-label="<?= $tr('common.bookform.chapter_remove') ?>">−</button>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="chapters__actions">
            <button type="button" class="btn btn--secondary" data-chapter-add>
              + <?= $tr('common.bookform.chapter_add') ?>
            </button>
          </div>

          <p class="book-form__hint"><?= $tr('common.bookform.chapters_hint') ?></p>
        </div>

        <h3 class="book-form__section-title"><?= $tr('common.bookform.section_files') ?></h3>

        <!-- ФАЙЛЫ: один input multiple на pdf/epub/mobi/fb2, минимум один
             файл. Список выбранных (имя, размер, минус) рисует book-form.js.
             Без JS уходит обычным POST — several files[] в multipart. -->
        <div class="form-field" data-files-field>
          <label for="bookFiles"><?= $tr('common.bookform.field_file') ?></label>

          <div class="file-upload" data-file-upload>
            <!-- Нативная строка браузера не переводится: скрытый input +
                 локализованная кнопка-лейбл и текст состояния. -->
            <input class="file-input-hidden"
                   type="file"
                   id="bookFiles"
                   name="files[]"
                   accept=".pdf,.epub,.mobi,.fb2,application/pdf,application/epub+zip,application/x-mobipocket-ebook,application/fb2"
                   multiple
                   data-files-input
                   required>
            <label class="btn btn--secondary file-upload__button" for="bookFiles">
              <span data-file-button-text><?= $tr('common.bookform.file_choose') ?></span>
              <span class="file-upload__button-state" data-file-button-state><?= $tr('common.bookform.file_none') ?></span>
            </label>

            <ul class="file-upload__list" data-files-list></ul>

            <p class="book-form__hint"><?= $tr('common.bookform.file_hint') ?></p>
          </div>

          <?= $errorBox('files') ?>
        </div>

        <!-- ============================================================
             ВРЕМЕННО НА ШАГЕ 2: списки авторов/редакторов.
             Шаг 3 «Роли» пропущен (см. договорённости в шапке файла) —
             когда его будут делать, эти два блока просто переносятся
             туда без изменений.
             ============================================================ -->
        <h3 class="book-form__section-title"><?= $tr('common.bookform.section_roles') ?></h3>

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
          <?= $errorBox('authors') ?>
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
          <?= $errorBox('editors') ?>
        </div>

        <!-- ============================================================
             НАВИГАЦИЯ ПОСЛЕДНЕГО (пока) ШАГА — как в article-form.
             Два режима:

             СОЗДАНИЕ («Назад/Отмена» + ДВА исхода отправки):
               Create draft → POST status=draft  → успех: редирект на
                            страницу книги (book-details) с бейджом
                            «Черновик» и кнопкой публикации внизу;
               Publish      → POST status=published → успех (проверка
                            прошла): открывается плашка выбора прав
                            (rights-modal.php, справочник грузится
                            асинхронно), после подтверждения — переход
                            на страницу книги.

             РЕДАКТИРОВАНИЕ (отмена → /books/{id}):
               Сохранить    → POST id>0, status = текущий статус книги
                            → успех: редирект на страницу книги;
               Опубликовать → ВСЕГДА видна; data-rights-open открывает
                            плашку выбора прав (rights-modal.js
                            mode='publish'): POST rights[] +
                            POST /books/{id}/publish → переход на
                            страницу книги (уже опубликованной).

             Невалидные данные (клиентские или 422 от бэка) возвращают
             пользователя на шаг с ошибкой: сначала первая страница
             (порядок полей в DOM), затем он сам перейдёт на вторую —
             см. focusFirstInvalid в book-form.js.
             Когда появится шаг 3 «Роли», блок действий переедет туда.
             ============================================================ -->
        <div class="book-step__nav">
          <button type="button" class="btn btn--secondary" data-step-prev>
            ← <?= $tr('common.bookform.back') ?>
          </button>
          <?php if ($isEdit): ?>
            <a class="btn btn--secondary"
               href="<?= $view->e($withLang('/books/' . $bookId)) ?>"><?= $tr('common.bookform.cancel') ?></a>
            <button class="btn btn--secondary" type="submit" data-submit-save>
              <?= $tr('common.bookform.save') ?>
            </button>
            <!-- Публикация существующей книги: открывает плашку выбора прав
                 (rights-modal.js, mode='publish') — книга публикуется после
                 подтверждения в плашке, не отправкой формы -->
            <button class="btn btn--primary" type="button"
                    data-rights-open data-book-id="<?= $bookId ?>">
              <?= $tr('common.bookform.publish') ?>
            </button>
          <?php else: ?>
            <a class="btn btn--secondary" href="<?= $view->e($withLang($view->url('books'))) ?>"><?= $tr('common.bookform.cancel') ?></a>
            <button class="btn btn--secondary" type="submit" name="status" value="draft" data-submit-draft>
              <?= $tr('common.bookform.create_draft') ?>
            </button>
            <button class="btn btn--primary" type="submit" name="status" value="published" data-submit-publish>
              <?= $tr('common.bookform.publish') ?>
            </button>
          <?php endif; ?>
        </div>
      </section>

    </form>

  </section>

  <!-- Плашка выбора прав: book-form открывает её после успешной публикации
       (rights-modal.js, контракт — в шапке partial) -->
  <?php $view->include('rights-modal'); ?>

</div>

<?php $view->endBlock('content'); ?>
