<?php
/**
 * avatar — круглая иконка пользователя.
 *
 * Ожидает:
 *   $size     — 'sm' | 'md' | 'lg' (по умолчанию md)
 *   $initials — буквы для заглушки
 *   $src      — URL картинки (опционально)
 */
$size     = $size     ?? 'md';
$initials = $initials ?? '';
$src      = $src      ?? null;
?>
<div class="avatar avatar--<?= $view->e($size) ?>">
  <?php if (!empty($src)): ?>
    <img src="<?= $view->e($src) ?>" alt="">
  <?php else: ?>
    <span><?= $view->e($initials) ?></span>
  <?php endif; ?>
</div>