<?php
/**
 * dropdown - dropdown component.
 *
 * @param string      $label   - trigger text
 * @param array       $options - [['label' => '...', 'href' => '...', 'value' => '...'], ...]
 * @param string|null $dynamic - optional JS marker: 'genres' etc.
 * @param string|null $key     - filter parameter name (for fetch filtering)
 */
$label   = $label   ?? 'Sort';
$options = $options ?? [];
$dynamic = $dynamic ?? null;
$key     = $key     ?? null;
?>
<div class="dropdown"
     data-dropdown
     data-label-base="<?= $view->e($label) ?>"
     <?= $key ? 'data-filter-key="' . $view->e($key) . '"' : '' ?>
     <?= $dynamic ? 'data-dynamic="' . $view->e($dynamic) . '"' : '' ?>>
  <button type="button" class="dropdown__trigger" data-dropdown-trigger>
    <span data-dropdown-label><?= $view->e($label) ?></span>
    <span class="dropdown__arrow">&#9662;</span>
  </button>

  <ul class="dropdown__menu">
    <?php foreach ($options as $opt): ?>
      <li>
        <a href="<?= $view->e($opt['href'] ?? '#') ?>"
           class="dropdown__item"
           <?= isset($opt['value']) ? 'data-filter-value="' . $view->e($opt['value']) . '"' : '' ?>>
          <?= $view->e($opt['label'] ?? '') ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>