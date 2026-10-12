<!DOCTYPE html>
<html lang="<?= $view->e($view->locale()) ?>">
<?php $view->include('head'); ?>
<body class="page page--login">

  <main class="login-card">

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
  </main>

  <!-- Переключатель языка: плавающая иконка в левом нижнем углу.
       Компонент — src/partials/lang-switch.php, стили — login.css -->
  <?php $view->include('lang-switch'); ?>

  <?= $view->block('scripts') ?>
</body>
</html>