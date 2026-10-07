<!DOCTYPE html>
<html lang="ru">
<?php $view->include('head'); ?>
<script src="/assets/js/card-feed.js" defer></script>
<!-- Профили: ник автора и @упоминания -> /users/{id} (используют book.js и card-feed.js) -->
<script src="<?= $view->asset('js/users.js') ?>"></script>
<body>

  <div class="sidebar-overlay" data-sidebar-overlay></div>

  <button type="button" class="burger" data-burger aria-label="Menu">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <?php $view->include('sidebar'); ?>

  <!-- Универсальная плашка подтверждения (Log out, удаление поста и т.п.) -->
  <?php $view->include('confirm-modal'); ?>

  <main class="content">
    <div class="content__inner">

      <?php if (!empty($innerMessages)): ?>
        <div class="messages">
          <?php foreach ($innerMessages as $msg): ?>
            <div class="message message--<?= $view->e($msg->type->value) ?>">
              <?php if (!empty($msg->title)): ?>
                <div class="message__title"><?= $view->e($msg->title) ?></div>
              <?php endif; ?>
              <div class="message__body"><?= $view->e($msg->message) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?= $view->block('content') ?>
    </div>
  </main>

  <script src="<?= $view->asset('js/modal.js') ?>"></script>
  <script src="<?= $view->asset('js/app.js') ?>"></script>
  <script src="<?= $view->asset('js/filters.js') ?>"></script>
  <?= $view->block('scripts') ?>
</body>
</html>