<?php
/**
 * book-edit-content.php — страница правки КОНТЕНТА книги.
 *
 * Подключается из book-form.php при GET /books/new?id=N&part=content
 * (новых роутов не добавляем — диспетчеризация по $_GET['part'] в
 * book-form.php). Все helpers ($val / $fromBook / $errorBox / $withLang /
 * $langField и т.д.) уже выполнены до точки подключения.
 *
 * ПОЛЯ — копия шага 2 book-form.php: аннотация, заметки автора, главы,
 * файлы книги + блок уже прикреплённых файлов (edit-режим, GET
 * /api/books/{id}/contents).
 *
 * ТОЛЬКО РАЗМЕТКА: book-form.js на эту страницу не подключается,
 * поэтому главы (добавление/удаление), список новых файлов и список
 * прикреплённых останутся нерабочими до подключения JS. Кнопка
 * «Сохранить» скрыта и без data-requires (permissions.js её не покажет),
 * <form> с onsubmit="return false;" блокирует нативный submit.
 *
 * Ссылки «Изменить контент» ведут сюда из details-menu.php.
 */
?>

<?php $view->startBlock('title'); ?><?= $tr('common.roles.edit_content') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/book-form.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.roles.edit_content'),
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
          data-edit-part="content"
          onsubmit="return false;"
          enctype="multipart/form-data"
          novalidate>
      <?= $view->csrfField() ?>
      <?= $langField ?>
      <!-- iconId (uuid обложки) и id книги — как в book-form.php -->
      <input type="hidden" name="iconId" value="<?= $view->e($val('iconId')) ?>">
      <input type="hidden" name="id" value="<?= $bookId ?>">

      <!-- ============================================================
           КОНТЕНТ И ФАЙЛЫ (бывший шаг 2 книги)
           ============================================================ -->
      <section class="book-step" data-step="2">
        <header class="book-step__header">
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
             имя уходит в POST /api/books/. Обязательное (бэк требует
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
             (контракт с бэкендом уточняется, см. book-form.php). -->
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

          <!-- edit-режим: файлы, УЖЕ прикреплённые к книге. PATCH
               /api/books/{id} contentIds[] не принимает, поэтому список
               живёт отдельно: GET /api/books/{id}/contents, удаление —
               DELETE .../contents/{contentId}, добавление новых — POST
               .../contents после успешного сохранения (book-form.js). -->
          <div class="file-upload" data-attached-files>
            <span class="form-field__label"><?= $tr('common.book.contents_title') ?></span>
            <ul class="file-upload__list" data-attached-list></ul>
            <p class="book-form__hint" data-attached-empty hidden>
              <?= $tr('common.book.contents_empty') ?>
            </p>
          </div>

          <?= $errorBox('files') ?>
        </div>

      </section>

      <!-- ============================================================
           НИЖНЯЯ ПАНЕЛЬ: «Отмена» ведёт на страницу книги, «Сохранить»
           СКРЫТА без data-requires — permissions.js её не покажет,
           пока не подключим префилл и сохранение.
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
