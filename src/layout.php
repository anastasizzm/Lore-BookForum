<?php
$active = $active ?? '';
$pageTitle = $pageTitle ?? 'Book App';
$pageActions = $pageActions ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>

  <div class="sidebar-overlay" data-sidebar-overlay></div>

  <button type="button" class="burger" data-burger aria-label="Меню">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="content">
    <div class="content__inner">
      <?= $content ?? '' ?>
    </div>
  </main>

  <script src="/assets/js/app.js"></script>
</body>
</html>