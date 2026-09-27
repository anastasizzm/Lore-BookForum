<!DOCTYPE html>
<html lang="ru">
<?php $view->include('head'); ?>
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
    </div>
  </main>

  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/filters.js"></script>
  <?= $view->block('scripts') ?>
</body>
</html>