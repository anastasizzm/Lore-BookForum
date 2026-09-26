<?php
/**
 * Ожидает:
 *   $size     — 'sm' | 'md' | 'lg' (по умолчанию md)
 *   $initials — буквы для заглушки
 *   $src      — URL картинки (опционально)
 */
$size = $size ?? 'md';
$initials = $initials ?? '';
$src = $src ?? null;
?>
<div class="avatar avatar--<?= $size ?>">
  <?php if (!empty($src)): ?>
    <img src="<?= htmlspecialchars($src) ?>" alt="">
  <?php else: ?>
    <span><?= htmlspecialchars($initials) ?></span>
  <?php endif; ?>
</div>