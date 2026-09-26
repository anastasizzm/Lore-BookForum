<?php
/**
 * Ожидает:
 *   $label   — текст на триггере
 *   $options — массив: [['label'=>'Popularity','href'=>'?sort=pop'], ...]
 */
$label = $label ?? 'Sort';
$options = $options ?? [];
?>
<div class="dropdown" data-dropdown>
  <button type="button" class="dropdown__trigger" data-dropdown-trigger>
    <?= htmlspecialchars($label) ?>
    <span class="dropdown__arrow">▾</span>
  </button>

  <ul class="dropdown__menu">
    <?php foreach ($options as $opt): ?>
      <li>
        <a href="<?= htmlspecialchars($opt['href'] ?? '#') ?>" class="dropdown__item">
          <?= htmlspecialchars($opt['label'] ?? '') ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>