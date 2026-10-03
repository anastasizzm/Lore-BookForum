<?php
$filter_rows = $filter_rows ?? [];
$filter_open = $filter_open ?? false;
?>
<div class="filter-panel" data-filter-panel <?= $filter_open ? '' : 'hidden' ?>>

  <?php foreach ($filter_rows as $row): ?>
    <div class="filter-panel__row"
         data-filter-row="<?= $view->e($row['id'] ?? '') ?>"
         <?= !empty($row['hidden']) ? 'hidden' : '' ?>>

      <?php foreach ($row['controls'] ?? [] as $control): ?>

        <?php if (($control['type'] ?? '') === 'tabs'): ?>
          <?php $view->include('tabs', [
              'variant' => $control['variant'] ?? 'filled',
              'items'   => $control['items']   ?? [],
          ]); ?>

        <?php elseif (($control['type'] ?? '') === 'dropdown'): ?>
          <?php $view->include('dropdown', [
              'label'   => $control['label']   ?? 'Select',
              'options' => $control['options'] ?? [],
              'dynamic' => $control['dynamic'] ?? null,
          ]); ?>

        <?php elseif (($control['type'] ?? '') === 'input'): ?>
          <input type="text"
                 class="filter-input"
                 name="<?= $view->e($control['name'] ?? '') ?>"
                 value="<?= $view->e($control['value'] ?? '') ?>"
                 placeholder="<?= $view->e($control['placeholder'] ?? '') ?>"
                 autocomplete="off"
                 spellcheck="false"
                 <?php if (!empty($control['format'])): ?>
                   data-format="<?= $view->e($control['format']) ?>"
                 <?php endif; ?>
                 <?php if (!empty($control['maxlength'])): ?>
                   maxlength="<?= (int)$control['maxlength'] ?>"
                 <?php endif; ?>
                 <?php if (!empty($control['inputmode'])): ?>
                   inputmode="<?= $view->e($control['inputmode']) ?>"
                 <?php endif; ?>>

        <?php elseif (($control['type'] ?? '') === 'reset'): ?>
          <a class="filter-reset"
             style="text-decoration:none"
             href="<?= $view->e($control['href'] ?? '?') ?>">
            &#10005; Reset
          </a>

        <?php endif; ?>

      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

</div>