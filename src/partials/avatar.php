<?php
/**
 * avatar — круглая иконка пользователя.
 *
 * Ожидает:
 *   $size     — 'sm' | 'md' | 'lg' (по умолчанию md)
 *   $initials — буквы для заглушки
 *   $src      — URL картинки (опционально)
 *
 * Аватар в настройках профиля — это НЕ файл, а пресет-эмодзи
 * (profile-edit.php: cat/dog/fox/owl/robot/star/book). Раньше сюда
 * передавали '/uploads/avatars/cat', файла не существовало — 404,
 * и вместо выбранного аватара показывались инициалы. Теперь путь
 * /uploads/avatars/{id} распознаётся как пресет и рисуется эмодзи;
 * в имени настоящего файла есть «.» — такой src остаётся <img>.
 *
 * Если картинка не загрузилась (404/битый URL) — onerror прячет <img>
 * и показывает инициалы, вместо сломанного изображения.
 */
$size     = $size     ?? 'md';
$initials = $initials ?? '';
$src      = $src      ?? null;

$avatarEmojis = require __DIR__ . '/avatar-presets.php';

$emoji = null;   // пресет-эмодзи из настроек
$file  = null;   // настоящая картинка

if (is_string($src) && $src !== '') {
    if (preg_match('#^/uploads/avatars/([^/]+)$#', $src, $m) && !str_contains($m[1], '.')) {
        // 'default' и неизвестные id не найдутся в карте -> $emoji = null -> инициалы
        $emoji = $avatarEmojis[$m[1]] ?? null;
    } else {
        $file = $src;   // '__SRC__' (шаблон для JS) или реальный URL файла
    }
}
?>
<div class="avatar avatar--<?= $view->e($size) ?>">
  <?php if ($emoji !== null): ?>
    <span class="avatar__emoji" aria-hidden="true"><?= $view->e($emoji) ?></span>
  <?php elseif ($file !== null): ?>
    <img src="<?= $view->e($file) ?>" alt=""
         onerror="this.hidden = true; var s = this.nextElementSibling; if (s) s.hidden = false;">
    <span hidden><?= $view->e($initials) ?></span>
  <?php else: ?>
    <span><?= $view->e($initials) ?></span>
  <?php endif ?>
</div>
