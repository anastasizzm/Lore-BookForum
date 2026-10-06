<?php
/**
 * avatar — круглая иконка пользователя.
 *
 * Ожидает:
 *   $size     — 'sm' | 'md' | 'lg' (по умолчанию md)
 *   $initials — буквы для заглушки
 *   $avatar   — ключ аватара из БД: 'cat', 'fox', ... или 'default'
 *               (список — src/avatar-presets.php). Если ключ неизвестен
 *               или это 'default' — показываются инициалы.
 */
$presets  = require __DIR__ . '/../avatar-presets.php';
$size     = $size     ?? 'md';
$initials = $initials ?? '';
$avatar   = (string)($avatar ?? '');
$emoji    = $presets[$avatar] ?? null;
?>
<div class="avatar avatar--<?= $view->e($size) ?><?= $emoji !== null ? ' avatar--emoji' : '' ?>">
  <span><?= $view->e($emoji ?? $initials) ?></span>
</div>