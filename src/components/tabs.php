<?php
/**
 * Ожидает:
 *   $variant — 'filled' | 'outline' (по умолчанию filled)
 *   $items   — массив: [['label'=>'All','href'=>'/saved?f=all','active'=>true], ...]
 */
$variant = $variant ?? 'filled';
$items = $items ?? [];
?>
<div class="tabs tabs--<?= $variant ?>">
  <?php foreach ($items as $item): ?>
    <a href="<?= htmlspecialchars($item['href'] ?? '#') ?>"
       class="tab <?= !empty($item['active']) ? 'is-active' : '' ?>">
      <?= htmlspecialchars($item['label'] ?? '') ?>
    </a>
  <?php endforeach; ?>
</div>