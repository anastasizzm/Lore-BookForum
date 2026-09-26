<?php
/**
 * tabs — табы/пилюли.
 *
 * Ожидает:
 *   $variant — 'filled' | 'outline' | 'segmented'
 *   $items   — массив:
 *     [
 *       'label'  => 'Книги',
 *       'href'   => '#books',
 *       'active' => true,
 *       'icon'   => '<svg>…</svg>',   // опционально
 *       'row'    => 'books',           // опционально, для data-row-target
 *     ]
 */
$variant = $variant ?? 'filled';
$items   = $items   ?? [];
?>
<div class="tabs tabs--<?= $view->e($variant) ?>">
  <?php foreach ($items as $item): ?>
    <a href="<?= $view->e($item['href'] ?? '#') ?>"
       class="tab <?= !empty($item['active']) ? 'is-active' : '' ?>"
       <?= isset($item['row']) ? 'data-row-target="' . $view->e($item['row']) . '"' : '' ?>>

      <?php if (!empty($item['icon'])): ?>
        <span class="tab__icon"><?= $item['icon'] ?></span>
      <?php endif; ?>

      <span class="tab__label"><?= $view->e($item['label'] ?? '') ?></span>
    </a>
  <?php endforeach; ?>
</div>