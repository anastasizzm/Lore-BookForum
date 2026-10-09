<?php
/**
 * avatar — круглая иконка пользователя.
 *
 * Ожидает:
 *   $icon     — id пресета из БД ('cat', 'fox'...). 'default'/пусто/неизвестный
 *               id -> показываются инициалы
 *   $size     — 'sm' | 'md' | 'lg' (по умолчанию md)
 *   $initials — буквы для заглушки
 *   $src      — URL настоящей картинки (опционально, для файла/JS-шаблона '__SRC__')
 *
 * Обратная совместимость: src вида /uploads/avatars/{id} (без точки)
 * по-прежнему распознаётся как пресет.
 *
 * Если картинка не загрузилась — onerror прячет <img> и показывает инициалы.
 */
$presets  = require __DIR__ . '/avatar-presets.php';
$size     = $size     ?? 'md';
$initials = $initials ?? '';
$icon     = $icon     ?? null;
$src      = $src      ?? null;

$preset = null;   // ['icon' => ..., 'bg' => ...]
$file   = null;   // настоящая картинка

if (is_string($icon) && isset($presets[$icon])) {
    $preset = $presets[$icon];
} elseif (is_string($src) && $src !== '') {
    if (preg_match('#^/uploads/avatars/([^/]+)$#', $src, $m) && !str_contains($m[1], '.')) {
        $preset = $presets[$m[1]] ?? null;
    } else {
        $file = $src;
    }
}
?>
<div class="avatar avatar--<?= $view->e($size) ?><?= $preset !== null ? ' avatar--preset' : '' ?>"
     <?= $preset !== null ? 'style="--avatar-bg: ' . $view->e($preset['bg']) . '"' : '' ?>>
  <?php if ($preset !== null): ?>
    <span class="avatar__emoji" aria-hidden="true"><?= $view->e($preset['icon']) ?></span>
  <?php elseif ($file !== null): ?>
    <img src="<?= $view->e($file) ?>" alt=""
         onerror="this.hidden = true; var s = this.nextElementSibling; if (s) s.hidden = false;">
    <span hidden><?= $view->e($initials) ?></span>
  <?php else: ?>
    <span><?= $view->e($initials) ?></span>
  <?php endif ?>
</div>