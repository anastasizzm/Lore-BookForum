<?php
/**
 * Partial: кнопка «Назад на дашборд» (ссылка на /admin).
 * Подключается в начале блока content каждой страницы админки:
 *   <?php $view->include('admin-back'); ?>
 */
$backLabel = $view->t('common.admin.back_to_dashboard');
if ($backLabel === '' || $backLabel === 'common.admin.back_to_dashboard') {
    $backLabel = 'Back to dashboard';
}
?>
<div class="admin-back" style="margin-bottom:16px">
  <a class="btn btn--secondary btn--sm" href="/admin" data-admin-back-link>&larr; <?= $view->e($backLabel) ?></a>
</div>
<script>
  // Если на сайте есть языковой префикс/параметр — подставляем его в ссылку
  (function () {
    var a = document.querySelector('[data-admin-back-link]');
    if (a && window.LoreI18n && typeof LoreI18n.withLang === 'function') {
      a.setAttribute('href', LoreI18n.withLang('/admin'));
    }
  })();
</script>