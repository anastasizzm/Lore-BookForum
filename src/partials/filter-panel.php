<?php
/**
 * filter-panel — универсальная панель фильтров.
 *
 * Ожидает:
 *   $filter_rows — массив рядов:
 *     [
 *       'id'       => 'books',
 *       'hidden'   => false,
 *       'controls' => [ ... ],
 *     ]
 *   $filter_open — bool, открыта ли панель по умолчанию (default false)
 *
 * Формат контрола:
 *   ['type' => 'tabs',     'variant' => 'filled', 'items' => [...]]
 *   ['type' => 'dropdown', 'label'   => 'Жанр',   'options' => [...]]
 *   ['type' => 'input',    'name'    => 'series', 'placeholder' => '...']
 *   ['type' => 'reset']
 */

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
              'label'   => $control['label']   ?? 'Выбрать',
              'options' => $control['options'] ?? [],
          ]); ?>

        <?php elseif (($control['type'] ?? '') === 'input'): ?>
          <input type="text"
                 class="filter-input"
                 name="<?= $view->e($control['name'] ?? '') ?>"
                 value="<?= $view->e($control['value'] ?? '') ?>"
                 placeholder="<?= $view->e($control['placeholder'] ?? '') ?>">

        <?php elseif (($control['type'] ?? '') === 'reset'): ?>
          <button type="button" class="filter-reset" data-filter-reset>
            ✕ Сбросить
          </button>

        <?php endif; ?>

      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

</div>