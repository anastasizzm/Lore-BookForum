<?php
/**
 * dropdown — выпадающий список.
 *
 * Ожидает:
 *   $label   — текст на триггере
 *   $options — массив: [['label' => '...', 'href' => '...'], ...]
 */
$label   = $label   ?? 'Sort';
$options = $options ?? [];
?>
<div class="dropdown" data-dropdown>
  <button type="button" class="dropdown__trigger" data-dropdown-trigger>
    <?= $view->e($label) ?>
    <span class="dropdown__arrow">▾</span>
  </button>

  <ul class="dropdown__menu">
    <?php foreach ($options as $opt): ?>
      <li>
        <a href="<?= $view->e($opt['href'] ?? '#') ?>" class="dropdown__item">
          <?= $view->e($opt['label'] ?? '') ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>