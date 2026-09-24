<header class="page-header">
  <h1 class="page-header__title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
  
  <?php if (!empty($pageActions)): ?>
    <div class="page-header__actions">
      <?= $pageActions ?>
    </div>
  <?php endif; ?>
</header>