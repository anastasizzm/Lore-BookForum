<?php
/**
 * Ожидает:
 *   $label    — текст (для 'icon' — необязателен, используется как aria-label)
 *   $variant  — 'primary' | 'secondary' | 'icon' (по умолчанию primary)
 *   $href     — если указан, рендерится <a>, иначе <button>
 *   $icon     — эмодзи/иконка (для 'primary'/'secondary' — слева от текста; для 'icon' — единственное содержимое)
 *   $class    — доп. классы (опционально)
 */
$variant = $variant ?? 'primary';
$tag = !empty($href) ? 'a' : 'button';
$class = $class ?? '';

// .btn-icon — самостоятельный класс в component.css, а не btn--icon
$baseClass = $variant === 'icon' ? 'btn-icon' : 'btn btn--' . $variant;
$classAttr = trim($baseClass . ' ' . $class);
?>
<<?= $tag ?>
  class="<?= htmlspecialchars($classAttr) ?>"
  <?= !empty($href) ? 'href="' . htmlspecialchars($href) . '"' : 'type="button"' ?>
  <?= $variant === 'icon' ? 'aria-label="' . htmlspecialchars($label ?? '') . '"' : '' ?>>
  <?php if ($variant === 'icon'): ?>
    <?= $icon ?? '' ?>
  <?php else: ?>
    <?php if (!empty($icon)): ?>
      <span class="btn__icon"><?= $icon ?></span>
    <?php endif; ?>
    <?= htmlspecialchars($label ?? '') ?>
  <?php endif; ?>
</<?= $tag ?>>