<?php
/**
 * Ожидает:
 *   $title — заголовок блока (опционально)
 *   $body  — HTML-содержимое
 */
?>
<section class="card-base info-box">
  <?php if (!empty($title)): ?>
    <h2 class="info-box__title"><?= htmlspecialchars($title) ?></h2>
  <?php endif; ?>
  <div class="info-box__body"><?= $body ?? '' ?></div>
</section>