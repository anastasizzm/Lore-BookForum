<!DOCTYPE html>
<html lang="ru">
<?php $view->include('head'); ?>

<!-- Аватары: список пресетов (src/avatar-presets.php) отдаём в JS,
     avatar.js должен подключаться ДО card-feed.js и book.js -->
<script>
window.AVATAR_PRESETS = <?= json_encode(
    require __DIR__ . '/../avatar-presets.php',
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
</script>
<script src="<?= $view->asset('js/avatar.js') ?>"></script>

<script src="/assets/js/card-feed.js" defer></script>
<body>

  <div class="sidebar-overlay" data-sidebar-overlay></div>

  <button type="button" class="burger" data-burger aria-label="Menu">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <?php /* sidebar — отдельный partial: видит только globals, поэтому текущего
          пользователя (для аватара) передаём явно */ ?>
  <?php $view->include('sidebar', ['user' => $user ?? null]); ?>

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