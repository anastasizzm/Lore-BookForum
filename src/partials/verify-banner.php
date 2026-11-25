<?php
/**
 * verify-banner — плашка «подтвердите почту» наверху каждой страницы.
 *
 * Показывается авторизованному пользователю без подтверждения email
 * ($currentUserId и $is_verified — общие глобалы из ViewGlobalsMiddleware).
 * Подключается в layouts/main.php:
 *   <?php $view->include('verify-banner'); ?>
 * Логика — public/assets/js/verify-banner.js, стили — public/assets/css/components/verify-banner.css.
 */
if (empty($currentUserId) || !empty($is_verified)) {
    return;
}

$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
?>
<div class="verify-banner" role="status" data-verify-banner>
  <p class="verify-banner__text"><?= $tr('common.verify_banner.text') ?></p>
  <button type="button"
          class="verify-banner__btn"
          data-verify-banner-send
          data-url="<?= $view->e($view->url('api.sendmail')) ?>">
    <?= $tr('common.verify_banner.button') ?>
  </button>
</div>
