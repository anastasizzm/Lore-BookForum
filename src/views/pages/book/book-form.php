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
 * КОНТРАКТ С БЭКОМ (app/ и config/ для фронта заморожены — роуты уже
 * заведены командой бэка):
 *
 *   GET  /books/new → роут books.create, AuthPolicy::Verified — рендерит
 *        ЭТУ вьюху, передавая $user (для сайдбара). Повторный рендер
 *        после 422 от веб-роута: $form (значения полей) и
 *        $errors (['title' => ['msg'], ...]).
 *
 *   POST /api/books/new → роут api.books.create.submit (config/
 *        endpoints.php), AuthPolicy::Verified + CSRF (поле _token или
 *        заголовок X-CSRF-Token). Accept: application/json.
 *        Тело — multipart/form-data (Request::body() отдаёт $_POST):
 *          title       (string, required, 1..255)
 *          description (string, required, 1..4000) — аннотация
 *          authorNotes (string, required, 1..2000) — заметки автора
 *          genreId     (int > 0, required) — GET /api/additional/genres
 *          categoryId  (int > 0, required) — GET /api/additional/categories
 *                       (UI — выпадающие списки select, выбор один: бэк
 *                       хранит ровно один жанр и одну категорию)
 *          publisher   (string, required, 1..255)
 *          pages       (int > 0, required)
 *          isbn        (string, optional, формат XXX-X-XXX-XXXXX-X —
 *                       книга ровно в дефисах, фронт расставляет их сам)
 *          iconId      (uuid, optional) — id обложки из POST
 *                       /api/files/upload (category=cover); без него бэк
 *                       ставит placeholder
 *          contentIds[] (array<uuid>, required ≥1) — id файлов книги из
 *                       POST /api/files/upload (category=book)
 *          _token, lang — CSRF и локаль
 *        ИМЕНА ПОЛЕЙ ОДИН В ОДИН = App\Forms\Publications\BookForm::
 *        fromInput() — переименовывать поля формы без бэка нельзя.
 *        успех: 201 { createdId: int } → book-form.js уводит на
 *               /books/{createdId}: книга создаётся ЧЕРНОВИКОМ
 *               (PublicationStatus::Draft), публикация — со страницы книги.
 *        ошибки: 422 { error: { code, message,
 *                              details: { field: [msg, ...] } } }
 *               (ExceptionMiddleware → ResponseTemplates::error) —
 *               book-form.js рисует их на полях и возвращает
 *               пользователя на шаг с первой ошибкой (порядок полей в DOM);
 *               401 — нет сессии, 419 — CSRF (см. book-form.js).
 *
 *   БИНАРНЫЕ ФАЙЛЫ — НЕ в этом POST: обложка и файлы книги грузятся
 *   отдельно через POST /api/files/upload (file + category →
 *   201 { createdId: uuid }), id подставляются в iconId / contentIds[].
 *   Поэтому payload /api/books/new собирается из полей формы, а файлы
 *   book-form.js передаст отдельным запросом (см. F2-02 ниже).
 *
 *   РЕЖИМ РЕДАКТИРОВАНИЯ: разметка зарезервирована ($book передаёт
 *   контроллер, когда появится GET /books/{id}/edit и endpoint
 *   обновления); отправка в edit-режиме блокируется в book-form.js —
 *   POST /api/books/new только создаёт и дубликат не увидит.
 *   Локальная копия (localStorage «Восстановить») — только для создания.
 *
 *   ПУБЛИКАЦИЯ И ПРАВА: плашка rights-modal (rights-modal.php/js) живёт
 *        ТОЛЬКО на book-details; из формы (11.4) публикация идёт второй
 *        фазой после создания черновика — POST /books/{id}/publish,
 *        при неудаче редирект на страницу книги (права можно выставить
 *        там). Права (rights-modal) из формы не выбираются.
 *   POST /books/{id}/publish            — черновик → published; используется
 *        плашкой на book-details (кнопка «Опубликовать книгу»), успех →
 *        перезагрузка страницы (бейдж и кнопка исчезают); с 11.4 —
 *        также кнопкой Publish в форме создания
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
 *   Сохранение файлов — POST /api/files/upload (FileController::upload,
 *   категория cover | book → 201 { createdId: uuid }); бэк сам
 *   проверяет MIME/размер (FilesValidator) и кладёт файл в storage.
 *
 * ---------------------------------------------------------------
 * МНОГОШАГОВОСТЬ (FORM-01, UI — по образцу article-form.php):
 *
 *   Шаг 1 «Данные книги» — формальности: название, жанр, категория,
 *                          издательство, страниц, ISBN, обложка.
 *   Шаг 2 «Содержание и файлы» — аннотация, заметки автора, главы,
 *                          файлы книги.
 *
 *   НИЖНЯЯ ПАНЕЛЬ (11.4): кнопки «Назад / Отмена / Создать черновик /
 *   Опубликовать» вынесены из шагов в общую панель .book-form__actions
 *   внутри <form> (sticky, видна на всех шагах); «Назад» скрыта на
 *   шаге 1. Publish: POST /api/books/new → 201 { createdId } →
 *   POST /books/{id}/publish; при неудаче publish — черновик уже
 *   создан, редирект на страницу книги (см. book-form.js).
 *
 *   ОКНО ПУБЛИКАЦИИ (11.3): списки авторов/редакторов переехали из
 *   шага 2 в модалку book-publish-dialog (partial, внутри <form>);
 *   кнопка Publish [data-publish-open] открывает её (только когда
 *   форма валидна), отправка — кнопкой внутри окна.
 *
 *   ШАГ 3 «РОЛИ» СОЗДАВАТЬСЯ НЕ БУДЕТ — пропущен по решению команды;
 *   списки людей живут в окне публикации (11.3).
 *
 *   Переход «Далее» прогоняет валидацию полей своего шага
 *   (см. book-form.js, STEP_FIELDS): без валидных обязательных полей
 *   дальше не пускает.
 *
 *   НЕСОХРАНЁННЫЕ ИЗМЕНЕНИЯ: книга пишется в localStorage
 *   (ключ bookform:v2:new) при вводе; при следующем открытии
 *   /books/new на шаге 1 показывается плашка «Восстановить /
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
 * Ошибка поля + алиасы: бэк отдаёт ключи BookForm (authorNotes,
 * genreId, …), старые веб-имена (author_notes, genre) подстраховываем.
 */
$errOf = static function (string $key) use ($err): string {
    $first = $err($key);
    if ($first !== '') return $first;
    $aliases = [
        'authorNotes' => ['author_notes', 'author_note'],
        'genreId'     => ['genre'],
        'categoryId'  => ['category'],
        'description' => ['annotation'],
    ];
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

/** Все имена полей формы — ключи BookForm + то, что рисует JS (cover/files). */
$fieldKeys = ['title', 'description', 'authorNotes', 'genreId', 'categoryId', 'publisher',
              'pages', 'isbn', 'iconId', 'contentIds', 'cover', 'files', 'authors', 'editors'];
/** Старые веб-имена полей: считаем их привязанными, чтобы не дублировать сверху. */
$fieldAliases = ['annotation', 'author_notes', 'author_note', 'genre', 'category', 'series'];

/** Ошибки, не привязанные к полю (например, «файл не сохранён»). */
$unboundErrors = [];
foreach ($errors as $field => $list) {
    if (in_array($field, $fieldKeys, true)) continue;
    if (in_array($field, $fieldAliases, true)) continue;
    foreach ((array) $list as $one) $unboundErrors[] = $one;
}

// Ошибки, которые JS ещё не разобрал (уведомление при загрузке, как в profile-edit).
$dirtyFields = [];
foreach ($fieldKeys as $key) {
    if ($errOf($key) !== '') $dirtyFields[] = $key;
}

$genreValue    = $val('genreId', $bookGenreId);
$categoryValue = $val('categoryId', $bookCategoryId);
?>

<?php $view->startBlock('title'); ?><?= $tr($isEdit ? 'common.bookform.title_edit' : 'common.bookform.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/book-form.css') ?>">
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

    <!-- Форма шлёт JSON-эндпоинт бэка: POST /api/books/new (роут
         api.books.create.submit в config/endpoints.php), multipart +
         CSRF (_token / X-CSRF-Token), Accept: application/json.
         ?lang= в action — чтобы бэк вернул ответ в той же локали. -->
    <form action="<?= $view->e($withLang('/api/books/new')) ?>"
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
      <!-- iconId (uuid обложки): book-form.js грузит картинку через
           POST /api/files/upload (category=cover) и вписывает сюда id.
           contentIds[] JS добавляет сам — по одному на файл книги. -->
      <input type="hidden" name="iconId" value="<?= $view->e($val('iconId')) ?>">
      <?php if ($isEdit): ?>
        <!-- edit-режим: id > 0; отправку блокирует book-form.js —
             endpoint'а обновления пока нет (см. шапку файла) -->
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
            <!-- Выпадающий список жанров: справочник GET /api/additional/genres
                 наполняет book-form.js (initCatalogSelect); выбор ОДИН — бэкенд
                 хранит ровно один genreId (int > 0). Select сам несёт name и
                 value (value — id из $form после 422 или книги при edit). -->
            <select class="form-field__input book-form__select"
                    id="bookGenre"
                    name="genreId"
                    data-catalog-select="genres"
                    data-placeholder="<?= $tr('common.bookform.select_placeholder') ?>">
              <?php if ((string) $genreValue !== ''): ?>
                <option value="<?= $view->e($genreValue) ?>" selected><?= $view->e($genreValue) ?></option>
              <?php else: ?>
                <option value="" selected disabled><?= $tr('common.bookform.select_placeholder') ?></option>
              <?php endif; ?>
            </select>
            <?= $errorBox('genreId') ?>
          </div>

          <div class="form-field">
            <label for="bookCategory"><?= $tr('common.bookform.field_category') ?></label>
            <!-- Выпадающий список категорий: GET /api/additional/categories,
                 выбор один (см. комментарий к жанру выше). -->
            <select class="form-field__input book-form__select"
                    id="bookCategory"
                    name="categoryId"
                    data-catalog-select="categories"
                    data-placeholder="<?= $tr('common.bookform.select_placeholder') ?>">
              <?php if ((string) $categoryValue !== ''): ?>
                <option value="<?= $view->e($categoryValue) ?>" selected><?= $view->e($categoryValue) ?></option>
              <?php else: ?>
                <option value="" selected disabled><?= $tr('common.bookform.select_placeholder') ?></option>
              <?php endif; ?>
            </select>
            <?= $errorBox('categoryId') ?>
          </div>

        </div>

        <div class="form-row--two-cols">

          <!-- Издательство и объём — обязательные поля BookForm
               (publisher: 1..255, pages: int > 0). -->
          <div class="form-field">
            <label for="bookPublisher"><?= $tr('common.bookform.field_publisher') ?></label>
            <input class="form-field__input"
                   type="text"
                   id="bookPublisher"
                   name="publisher"
                   value="<?= $view->e($val('publisher', $fromBook('publisher'))) ?>"
                   maxlength="255"
                   autocomplete="off"
                   placeholder="<?= $tr('common.bookform.field_publisher_ph') ?>"
                   required>
            <?= $errorBox('publisher') ?>
          </div>

          <div class="form-field">
            <label for="bookPages"><?= $tr('common.bookform.field_pages') ?></label>
            <input class="form-field__input"
                   type="number"
                   id="bookPages"
                   name="pages"
                   value="<?= $view->e($val('pages', $fromBook('pages'))) ?>"
                   min="1"
                   step="1"
                   inputmode="numeric"
                   autocomplete="off"
                   placeholder="<?= $tr('common.bookform.field_pages_ph') ?>"
                   required>
            <?= $errorBox('pages') ?>
          </div>

        </div>

        <!-- ISBN необязательный; форма и JS проверяют ISBN-13 (13 цифр,
             префикс 978/979), бэк принимает только XXX-X-XXX-XXXXX-X —
             дефисы расставляет book-form.js. Подсказка — js.isbn_hint. -->
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

        <!-- ОБЛОЖКА: превью показывает book-form.js (object URL).
             У input НЕТ name: бинарник не входит в payload /api/books/new —
             book-form.js грузит его отдельным POST /api/files/upload
             (category=cover) и кладёт uuid в скрытое поле iconId. -->
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
                    name="description"
                    rows="6"
                    maxlength="4000"
                    placeholder="<?= $tr('common.bookform.field_annotation_ph') ?>"><?= $view->e($val('description', $fromBook('description'))) ?></textarea>
          <?= $errorBox('description') ?>
        </div>

        <!-- Заметки автора: поле BookForm authorNotes (1..2000), то же
             имя уходит в POST /api/books/new. Обязательное (бэк требует
             непустые 1..2000) — required + клиентская проверка на пустоту
             и длину (11.2, см. RULES в book-form.js). -->
        <div class="form-field">
          <label for="bookAuthorNotes"><?= $tr('common.bookform.field_author_note') ?></label>
          <textarea class="form-field__input form-field__input--textarea"
                    id="bookAuthorNotes"
                    name="authorNotes"
                    rows="4"
                    maxlength="2000"
                    required
                    placeholder="<?= $tr('common.bookform.author_note_ph') ?>"><?= $view->e($val('authorNotes', $fromBook('authorNotes'))) ?></textarea>
          <?= $errorBox('authorNotes') ?>
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
             У input НЕТ name: файлы уходят отдельными запросами
             POST /api/files/upload (category=book) → contentIds[]. -->
        <div class="form-field" data-files-field>
          <label for="bookFiles"><?= $tr('common.bookform.field_file') ?></label>

          <div class="file-upload" data-file-upload>
            <!-- Нативная строка браузера не переводится: скрытый input +
                 локализованная кнопка-лейбл и текст состояния. -->
            <input class="file-input-hidden"
                   type="file"
                   id="bookFiles"
                   accept=".pdf,.epub,.mobi,.fb2,application/pdf,application/epub+zip,application/x-mobipocket-ebook,application/fb2"
                   multiple
                   data-files-input>
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
             СПИСКИ АВТОРОВ/РЕДАКТОРОВ (11.3) переехали в окно
             публикации — partial book-publish-dialog (ниже, внутри
             <form>), открывается кнопкой Publish в нижней панели.
             ============================================================ -->

        <!-- Кнопки действий ушли в общую нижнюю панель
             .book-form__actions (11.4) — см. ниже, перед </form>. -->

        <!-- ============================================================
             Невалидные данные (клиентские или 422 от бэка) возвращают
             пользователя на шаг с ошибкой: сначала первая страница
             (порядок полей в DOM), затем он сам перейдёт на вторую —
             см. focusFirstInvalid в book-form.js.
             ============================================================ -->
      </section>

      <!-- ============================================================
           НИЖНЯЯ ПАНЕЛЬ ДЕЙСТВИЙ ФОРМЫ (11.4): sticky, видна на всех
           шагах. «Назад» скрыта на шаге 1 (book-form.js, initSteps).

           СОЗДАНИЕ («Отмена» + Create draft / Publish):
             Create draft → POST /api/books/new → 201 { createdId } →
                          редирект на страницу книги: бэк создаёт
                          только черновик.
             Publish → тот же POST /api/books/new (черновик), затем
                       POST /books/{id}/publish (P0-маршрут, см.
                       rights-modal.php). Если publish не прошёл —
                       черновик уже создан, поэтому уводим на страницу
                       книги с предупреждением (публикация возможна
                       и с неё).

           РЕДАКТИРОВАНИЕ (зарезервировано, см. шапку файла): отправка
           блокируется в book-form.js — endpoint'а обновления нет,
           а /api/books/new только создаёт.
           ============================================================ -->
      <div class="book-form__actions">
        <button type="button" class="btn btn--secondary" data-step-prev hidden>
          ← <?= $tr('common.bookform.back') ?>
        </button>
        <div class="book-form__actions-right">
          <?php if ($isEdit): ?>
            <a class="btn btn--secondary"
               href="<?= $view->e($withLang('/books/' . $bookId)) ?>"><?= $tr('common.bookform.cancel') ?></a>
            <button class="btn btn--primary" type="submit" data-submit-save>
              <?= $tr('common.bookform.save') ?>
            </button>
          <?php else: ?>
            <a class="btn btn--secondary" href="<?= $view->e($withLang($view->url('books'))) ?>"><?= $tr('common.bookform.cancel') ?></a>
            <button class="btn btn--secondary" type="submit" data-submit-draft>
              <?= $tr('common.bookform.create_draft') ?>
            </button>
            <!-- Publish (11.3): открывает окно выбора авторов/редакторов
                 (book-publish-dialog); отправка — кнопкой внутри окна. -->
            <button class="btn btn--primary" type="button" data-publish-open>
              <?= $tr('common.bookform.publish') ?>
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- ============================================================
           ОКНО ПУБЛИКАЦИИ (11.3): авторы/редакторы переехали из шага 2
           в book-publish-dialog (partial). Publish в нижней панели —
           кнопка-открыватель [data-publish-open]; отправка — кнопкой
           внутри окна (data-submit-publish).
           Окно лежит ВНУТРИ <form>: hidden inputs authors[]/editors[]
           должны попадать в FormData.
           ============================================================ -->
      <?php $view->include('book-publish-dialog', [
          'authors'    => $authors,
          'editors'    => $editors,
          'errorBoxFn' => $errorBox,
      ]); ?>

    </form>

  </section>

</div>

<?php $view->endBlock('content'); ?>
