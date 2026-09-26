<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $view->block('title') ?></title>
  <link rel="stylesheet" href="/assets/css/variable.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
  <link rel="stylesheet" href="/assets/css/component.css">
</head>
<body>

  <div class="sidebar-overlay" data-sidebar-overlay></div>

  <button type="button" class="burger" data-burger aria-label="Меню">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <?php $view->include('sidebar'); ?>

  <main class="content">
    <div class="content__inner">
      <?= $view->block('content') ?>
    </div>
  </main>

  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/filters.js"></script>
</body>
</html>