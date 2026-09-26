<!DOCTYPE html>
<html lang="en">
<?php $view->include('head', ['pageTitle' => $pageTitle ?? 'Lore']); ?>
<body class="page page--login">

  <main class="login-card">
    <?= $view->block('content') ?>
  </main>

  <?= $view->block('scripts') ?>
</body>
</html>
