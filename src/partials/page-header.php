<header class="page-header">
  <div class="page-header-row">
    <button type="button" class="burger" data-burger aria-label="Меню">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <h1 class="page-header__title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
  </div>
  
  <?php if (!empty($pageActions)): ?>
    <div class="page-header__actions">
      <?= $pageActions ?>
    </div>
  <?php endif; ?>
</header>