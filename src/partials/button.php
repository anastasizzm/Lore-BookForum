<?php
/**
 * button — кнопка или ссылка-кнопка.
 *
 * Ожидает:
 *   $label   — текст (для 'icon' — используется как aria-label)
 *   $variant — 'primary' | 'secondary' | 'icon' (по умолчанию primary)
 *   $href    — если указан, рендерится <a>, иначе <button>
 *   $icon    — эмодзи/иконка (HTML)
 *   $class   — доп. классы (опционально)
 */
$variant = $variant ?? 'primary';
$tag     = !empty($href) ? 'a' : 'button';
$class   = $class ?? '';

$baseClass = $variant === 'icon' ? 'btn-icon' : 'btn btn--' . $variant;
$classAttr = trim($baseClass . ' ' . $class);
?>
<<?= $tag ?>
  class="<?= $view->e($classAttr) ?>"
  <?= !empty($href) ? 'href="' . $view->e($href) . '"' : 'type="button"' ?>
  <?= $variant === 'icon' ? 'aria-label="' . $view->e($label ?? '') . '"' : '' ?>>
  <?php if ($variant === 'icon'): ?>
    <?= $icon ?? '' ?>
  <?php else: ?>
    <?php if (!empty($icon)): ?>
      <span class="btn__icon"><?= $icon ?></span>
    <?php endif; ?>
    <?= $view->e($label ?? '') ?>
  <?php endif; ?>
</<?= $tag ?>>