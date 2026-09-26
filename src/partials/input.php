<?php
/**
 * $type        - html-тип input (например 'text', 'email', 'password', 'search')
 * $name        - имя поля
 * $placeholder - текст-подсказка
 * $value       - значение
 * (визуальный вариант "search" применяется автоматически при $type === 'search';
 *  для остальных типов используется базовый .input)
 */
$type = $type ?? 'text';
$name = $name ?? 'input';
$placeholder = $placeholder ?? '';
$value = $value ?? '';
$isSearch = $type === 'search';
?>
<div class="input<?= $isSearch ? ' input--search' : '' ?>">
  <?php if ($isSearch): ?>
    <span class="input__icon">
        <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="7.5" cy="7.5" r="6.5" stroke="#5876A6" stroke-width="2"/>
        <line x1="14.4142" y1="15" x2="18.6569" y2="19.2426" stroke="#5876A6" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </span>
  <?php endif; ?>
  <input type="<?= htmlspecialchars($type) ?>" name="<?= htmlspecialchars($name) ?>" placeholder="<?= htmlspecialchars($placeholder) ?>" value="<?= htmlspecialchars($value) ?>" class="input__field">
</div>