<?php
$pageTitle    = $pageTitle    ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$pageActions  = $pageActions  ?? '';
?>
<header class="page-header">

  <div class="page-header-row">
    <button type="button" class="burger" data-burger aria-label="Меню">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <div class="page-header__titles">
      <h1 class="page-header__title"><?= htmlspecialchars($pageTitle) ?></h1>
      <?php if ($pageSubtitle !== ''): ?>
        <p class="page-header__subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($pageActions)): ?>
    <div class="page-header__actions">
      <?= $pageActions ?>
    </div>
  <?php endif; ?>

</header>