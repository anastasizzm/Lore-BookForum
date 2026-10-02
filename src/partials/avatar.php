<?php
/**
 * avatar — круглая иконка пользователя.
 *
 * Ожидает:
 *   $size     — 'sm' | 'md' | 'lg' (по умолчанию md)
 *   $initials — буквы для заглушки
 *   $src      — URL картинки (опционально)
 *
 * Если картинка не загрузилась (404/битый URL) — onerror прячет <img>
 * и показывает инициалы, вместо сломанного изображения.
 */
$size     = $size     ?? 'md';
$initials = $initials ?? '';
$src      = $src      ?? null;
$hasSrc   = is_string($src) && $src !== '';
?>
<div class="avatar avatar--<?= $view->e($size) ?>">
  <?php if ($hasSrc): ?>
    <img src="<?= $view->e($src) ?>" alt=""
         onerror="this.hidden = true; var s = this.nextElementSibling; if (s) s.hidden = false;">
    <span hidden><?= $view->e($initials) ?></span>
  <?php else: ?>
    <span><?= $view->e($initials) ?></span>
  <?php endif ?>
</div>
