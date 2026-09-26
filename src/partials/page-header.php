<?php
/**
 * page-header — верх страницы: заголовок, подзаголовок, actions.
 *
 * Ожидает:
 *   $title    — заголовок страницы
 *   $subtitle — подзаголовок (опционально)
 *   $actions  — HTML правого блока (поиск, кнопки) (опционально)
 */
$title    = $title    ?? '';
$subtitle = $subtitle ?? '';
$actions  = $actions  ?? '';
?>
<header class="page-header">

  <div class="page-header__titles">
    <h1 class="page-header__title"><?= $view->e($title) ?></h1>
    <?php if (!empty($subtitle)): ?>
      <p class="page-header__subtitle"><?= $view->e($subtitle) ?></p>
    <?php endif; ?>
  </div>

  <?php if (!empty($actions)): ?>
    <div class="page-header__actions">
      <?= $actions ?>
    </div>
  <?php endif; ?>

</header>