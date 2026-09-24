<?php
/**
 * Ожидает:
 *   $label    — текст
 *   $variant  — 'primary' | 'secondary' | 'icon' (по умолчанию primary)
 *   $href     — если указан, рендерится <a>, иначе <button>
 *   $icon     — эмодзи/иконка слева (опционально)
 *   $class    — доп. классы (опционально)
 */
$variant = $variant ?? 'primary';
$tag = !empty($href) ? 'a' : 'button';
$class = $class ?? '';
?>
<<?= $tag ?>
  class="btn btn--<?= $variant ?> <?= $class ?>"
  <?= !empty($href) ? 'href="' . htmlspecialchars($href) . '"' : 'type="button"' ?>>
  <?php if (!empty($icon)): ?>
    <span class="btn__icon"><?= $icon ?></span>
  <?php endif; ?>
  <?= htmlspecialchars($label ?? '') ?>
</<?= $tag ?>>