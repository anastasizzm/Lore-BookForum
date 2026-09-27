<!DOCTYPE html>
<html lang="en">
<?php $view->include('head'); ?>
<body class="page page--login">

  <main class="login-card">

    <?php if (!empty($innerMessages)): ?>
      <div class="messages">
        <?php foreach ($innerMessages as $msg): ?>
          <div class="message message--<?= $view->e($msg['type']) ?>">
            <?php if (!empty($msg['title'])): ?>
              <div class="message__title"><?= $view->e($msg['title']) ?></div>
            <?php endif; ?>
            <div class="message__body"><?= $view->e($msg['body']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?= $view->block('content') ?>
  </main>

  <?= $view->block('scripts') ?>
</body>
</html>