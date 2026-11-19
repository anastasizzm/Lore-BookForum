<?php

/**
 * book-publish-dialog — LEGACY-окно авторов/редакторов (11.3).
 *
 * НЕ ИСПОЛЬЗУЕТСЯ: Publish в book-form.js больше не открывает это окно —
 * черновик создаётся сразу, а людей/роли назначают в roles-module.
 * Разметка оставлена только как исторический блок; корень помечен
 * data-legacy-unused="1" и hidden. Новые сценарии сюда не добавлять.
 *
 * ВАЖНО: если partial снова подключат внутри <form>, hidden inputs
 * authors[]/editors[] попадут в FormData, но бэк эти поля не читает.
 *
 * Стили: каркас .modal — modal.css; размеры/скролл — book-form.css
 * (.book-publish-dialog__*).
 *
 * Структура (как у article-rights-dialog): заголовок, подсказка,
 * тело со списками, футер с отменой и подтверждением.
 */

$tr = static fn(string $k, array $p = []): string => $view->e($view->t($k, $p));

/** Списки людей и слоты ошибок передаёт book-form.php. */
$authors     = $authors     ?? [];
$editors     = $editors     ?? [];
$errorBoxFn  = $errorBoxFn  ?? null;
?>
<div class="modal book-publish-dialog" data-book-publish-dialog data-legacy-unused="1"
     role="dialog" aria-modal="true" aria-labelledby="bookPublishDialogTitle" hidden>

  <div class="modal__backdrop" data-publish-dialog-backdrop></div>

  <div class="modal__dialog book-publish-dialog__dialog">

    <h2 class="modal__title" id="bookPublishDialogTitle">
      <?= $tr('common.bookform.section_roles') ?>
    </h2>

    <p class="modal__text book-publish-dialog__hint">
      <?= $tr('common.bookform.publish_dialog_hint') ?>
    </p>

    <div class="book-publish-dialog__body">

      <!-- АВТОРЫ: поиск людей по нику (GET /api/users?q=),
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
        <?php if ($errorBoxFn) echo $errorBoxFn('authors') ?>
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
        <?php if ($errorBoxFn) echo $errorBoxFn('editors') ?>
      </div>

    </div>

    <div class="modal__actions">
      <button type="button" class="btn btn--secondary" data-publish-dialog-close>
        <?= $tr('common.cancel') ?>
      </button>
      <button type="submit" class="btn btn--primary" data-submit-publish>
        <?= $tr('common.bookform.publish') ?>
      </button>
    </div>

  </div>
</div>
