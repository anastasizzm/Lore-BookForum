<?php
/**
 * Ожидает:
 *   $variant — 'filled' | 'outline' | 'segmented' (по умолчанию filled)
 *   $items   — массив:
 *     [
 *       'label'  => 'Книги',
 *       'href'   => '#books',
 *       'active' => true,
 *       'icon'   => '<svg>…</svg>',   // опционально, вставится до текста
 *       'row'    => 'books',           // опционально, для data-row-target
 *     ]
 */
$variant = $variant ?? 'filled';
$items = $items ?? [];
?>
<div class="tabs tabs--<?= $variant ?>">
  <?php foreach ($items as $item): ?>
    <a href="<?= htmlspecialchars($item['href'] ?? '#') ?>"
       class="tab <?= !empty($item['active']) ? 'is-active' : '' ?>"
       <?= isset($item['row']) ? 'data-row-target="' . htmlspecialchars($item['row']) . '"' : '' ?>>

      <?php if (!empty($item['icon'])): ?>
        <span class="tab__icon"><?= $item['icon'] ?></span>
      <?php endif; ?>

      <span class="tab__label"><?= htmlspecialchars($item['label'] ?? '') ?></span>
    </a>
  <?php endforeach; ?>
</div>