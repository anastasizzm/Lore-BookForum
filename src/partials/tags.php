<?php
/**
 * tags — иконка + число (лайки, комментарии).
 *
 * Ожидает:
 *   $icon  — эмодзи иконки
 *   $value — число
 */
$icon  = $icon  ?? '';
$value = $value ?? 0;
?>
<span class="tag">
  <span class="tag__icon"><?= $icon ?></span>
  <span class="tag__value"><?= (int)$value ?></span>
</span>