<?php
/**
 * book-edit-meta.php — страница правки МЕТАДАННЫХ книги.
 *
 * Подключается из book-form.php при GET /books/new?id=N&part=meta
 * (новых роутов не добавляем: роут books.create рендерит book-form.php,
 * а та диспетчеризует по $_GET['part']). Весь код выше точки подключения
 * (helpers $val / $fromBook / $errorBox / $withLang / $langField и т.д.)
 * уже выполнен — отсюда доступны $view, $tr, $bookId, $isEdit и пр.
 *
 * ПОЛЯ — копия шага 1 book-form.php: название, жанр, категория,
 * издательство, страницы, ISBN, обложка. Шагов нет (это одна страница),
 * блок «Восстановить» и кнопка «Далее» — только у создания, здесь их нет.
 *
 * ТОЛЬКО РАЗМЕТКА: префилла и отправки пока нет — book-form.js на эту
 * страницу не подключается (иначе он начал бы PATCH /api/books/{id}),
 * поэтому справочники жанра/категории и превью обложки останутся
 * пустыми до подключения JS. Кнопка «Сохранить» отрендерена СКРЫТОЙ и
 * БЕЗ data-requires — permissions.js её не покажет, пока не появится
 * обработчик отправки. <form> с onsubmit="return false;" не даёт
 * нативному submit (Enter в поле) уйти на POST /api/books/.
 *
 * Ссылки «Изменить мета-данные» ведут сюда из details-menu.php
 * (дропдаун «три точки» на странице книги).
 */
?>

<?php $view->startBlock('title'); ?><?= $tr('common.roles.edit_meta') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/forms/book-form.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.roles.edit_meta'),
    'subtitle' => $view->t('common.bookform.subtitle'),
]); ?>

<div class="book-form-grid">

  <section class="book-form card-base">

    <!-- Тот же контракт, что у book-form.php (POST /api/books/, multipart
         + CSRF, ?lang= в action); отправка пока заблокирована onsubmit. -->
    <form action="<?= $view->e($withLang('/api/books/')) ?>"
          method="POST"
          id="bookForm"
          data-book-form
          data-edit-part="meta"
          onsubmit="return false;"
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>
      <?= $langField ?>
      <!-- iconId (uuid обложки) и id книги — как в book-form.php;
           book-form.js (когда подключим) уйдёт PATCH /api/books/{id} -->
      <input type="hidden" name="iconId" value="<?= $view->e($val('iconId')) ?>">
      <input type="hidden" name="id" value="<?= $bookId ?>">

      <!-- ============================================================
           МЕТАДАННЫЕ (бывший шаг 1 книги)
           ============================================================ -->
      <section class="book-step" data-step="1">
        <header class="book-step__header">
          <h2 class="book-step__title"><?= $tr('common.bookform.step1_title') ?></h2>
        </header>

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
                 наполняет book-form.js (initCatalog-select); выбор ОДИН. -->
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
             У input НЕТ name: бинарник не входит в payload /api/books/ —
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

      </section>

      <!-- ============================================================
           НИЖНЯЯ ПАНЕЛЬ: «Отмена» ведёт на страницу книги, «Сохранить»
           СКРЫТА без data-requires — permissions.js её не покажет,
           пока не подключим префилл и PATCH /api/books/{id}.
           ============================================================ -->
      <div class="book-form__actions">
        <div class="book-form__actions-right">
          <a class="btn btn--secondary"
             href="<?= $view->e($withLang('/books/' . $bookId)) ?>"><?= $tr('common.bookform.cancel') ?></a>

          <button class="btn btn--primary" type="submit" data-submit-save hidden>
            <?= $tr('common.bookform.save') ?>
          </button>
        </div>
      </div>

    </form>

  </section>

</div>

<?php $view->endBlock('content'); ?>
