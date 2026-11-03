<?php
/**
 * partials/admin-role-create-modal — окно «Новая роль» (admin/roles).
 *
 * Логика — public/assets/js/admin.js (раздел ROLE CREATE).
 * Поля: название (EN) → «+ язык» → Private/Public → цвет → Save.
 */

// Если ключа перевода ещё нет, показываем запасной текст, а не сам ключ.
$tx = static function (string $key, string $fallback) use ($view): string {
    $value = $view->t($key);
    return $view->e(($value === '' || $value === $key) ? $fallback : $value);
};

$colors = ['#D64545', '#1C7ED6', '#2F9E44', '#F08C00', '#7048E8', '#0CA678', '#D6336C', '#495057'];
?>
<div class="modal" data-rolenew-modal hidden>
  <div class="modal__backdrop" data-rolenew-close></div>

  <div class="modal__dialog admin-rolenew" role="dialog" aria-modal="true" aria-labelledby="rolenew-title">
    <button type="button" class="admin-rolenew__close" data-rolenew-close
            aria-label="<?= $tx('common.common.close', 'Close') ?>">
      <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
        <path d="M3 3l10 10M13 3L3 13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
    </button>

    <form class="admin-rolenew__form" data-rolenew-form novalidate>
      <h2 class="admin-rolenew__title" id="rolenew-title"><?= $tx('common.admin.role_new_title', 'New role') ?></h2>

      <p class="admin-rolenew__error" data-rolenew-error role="alert" hidden></p>

      <section class="admin-rolenew__section">
        <label class="admin-rolenew__label" for="rolenew-name">
          <?= $tx('common.admin.role_new_name', 'Role name (English)') ?>
        </label>
        <input class="form-field__input" id="rolenew-name" type="text" data-rolenew-name
               maxlength="100" autocomplete="off" required>

        <ul class="admin-rolenew__langs" data-rolenew-langs></ul>

        <div class="admin-rolenew__dd" data-rolenew-dd>
          <button type="button" class="admin-rolenew__add" data-rolenew-lang-toggle
                  aria-haspopup="listbox" aria-expanded="false">
            <span aria-hidden="true">+</span>
            <?= $tx('common.admin.role_new_add_lang', 'Add language') ?>
          </button>
          <ul class="admin-rolenew__menu" data-rolenew-menu role="listbox" hidden></ul>
        </div>
      </section>

      <fieldset class="admin-rolenew__section">
        <legend class="admin-rolenew__label"><?= $tx('common.admin.role_new_visibility', 'Visibility') ?></legend>
        <div class="admin-rolenew__pill">
          <label>
            <input type="radio" name="visibility" value="private">
            <span><?= $tx('common.admin.role_private', 'Private') ?></span>
          </label>
          <label>
            <input type="radio" name="visibility" value="public" checked>
            <span><?= $tx('common.admin.role_public', 'Public') ?></span>
          </label>
        </div>
      </fieldset>

      <fieldset class="admin-rolenew__section">
        <legend class="admin-rolenew__label"><?= $tx('common.admin.role_new_color', 'Color') ?></legend>
        <div class="admin-rolenew__colors">
          <?php foreach ($colors as $i => $hex): ?>
            <label class="admin-rolenew__swatch" title="<?= $view->e($hex) ?>">
              <input type="radio" name="color" value="<?= $view->e($hex) ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <span style="background: <?= $view->e($hex) ?>"></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="admin-rolenew__footer">
        <button type="submit" class="btn admin-rolenew__save" data-rolenew-submit="save">
          <?= $tx('common.admin.role_save_only', 'Save') ?>
        </button>
      </div>
    </form>
  </div>
</div>