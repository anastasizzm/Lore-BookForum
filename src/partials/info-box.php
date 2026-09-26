<?php
/**
 * info-box — простая белая карточка с заголовком.
 *
 * Ожидает:
 *   $title — заголовок (опционально)
 *   $body  — HTML-содержимое
 */
$title = $title ?? '';
$body  = $body  ?? '';
?>
<section class="card-base info-box">
  <?php if (!empty($title)): ?>
    <h2 class="info-box__title"><?= $view->e($title) ?></h2>
  <?php endif; ?>
  <div class="info-box__body"><?= $body ?></div>
</section>