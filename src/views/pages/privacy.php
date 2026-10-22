<?php $view->extends('main'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
/*
 * privacy — статическая страница «Политика конфиденциальности» (задача 6).
 *
 * Сделана по аналогии с help.php (задача 5): тот же каркас, оглавление
 * с якорями и разделы с иконками. Роута privacy бэк ещё не завёл
 * (config/routes.php заморожен) — страница открывается через тест-роут:
 *   /pages/test?page=privacy
 * На кнопку «Privacy Policy» в сайдбаре (src/partials/sidebar.php) страница
 * открывается по той же ссылке — когда роут появится, заменить путь в
 * sidebar.php на $url('privacy'), остальное уже готово.
 *
 * Контент полностью в resources/lang/{en,ru}/common.json, секция privacy —
 * страница статическая, JS не нужен.
 *
 * Стили — public/assets/css/privacy.css (.privacy-*); каркас панели — общие
 * .books-panel (component.css), шапка — partial page-header.
 */

$view->setBlock('selectedTab', 'privacy');

/**
 * Разделы страницы: id якоря, ключи перевода заголовка и текста,
 * ключи пунктов списка (если есть). Порядок — как на странице.
 */
$sections = [
    ['id' => 'intro',    'title' => 'common.privacy.intro_title',    'text' => 'common.privacy.intro_text'],
    ['id' => 'collect',  'title' => 'common.privacy.collect_title',  'items' => ['common.privacy.collect_1', 'common.privacy.collect_2', 'common.privacy.collect_3', 'common.privacy.collect_4']],
    ['id' => 'use',      'title' => 'common.privacy.use_title',      'items' => ['common.privacy.use_1', 'common.privacy.use_2', 'common.privacy.use_3']],
    ['id' => 'share',    'title' => 'common.privacy.share_title',    'items' => ['common.privacy.share_1', 'common.privacy.share_2']],
    ['id' => 'cookies',  'title' => 'common.privacy.cookies_title',  'items' => ['common.privacy.cookies_1']],
    ['id' => 'rights',   'title' => 'common.privacy.rights_title',   'items' => ['common.privacy.rights_1', 'common.privacy.rights_2']],
    ['id' => 'security', 'title' => 'common.privacy.security_title', 'text' => 'common.privacy.security_text'],
    ['id' => 'contact',  'title' => 'common.privacy.contact_title',  'text' => 'common.privacy.contact_text'],
];

/** Иконка раздела: простой SVG в стиле иконок сайдбара (currentColor). */
$sectionIcons = [
    'intro'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.6v.2"/>',
    'collect'  => '<ellipse cx="12" cy="6" rx="7.5" ry="3"/><path d="M4.5 6v12c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3V6"/><path d="M4.5 12c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3"/>',
    'use'      => '<path d="M4 7h10"/><path d="M18 7h2"/><path d="M4 17h6"/><path d="M14 17h6"/><circle cx="16" cy="7" r="2.2"/><circle cx="12" cy="17" r="2.2"/>',
    'share'    => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
    'cookies'  => '<path d="M12 3.5a8.5 8.5 0 1 0 8.5 8.5 4 4 0 0 1-4.5-4.5A4 4 0 0 1 12 3.5Z"/><circle cx="9" cy="10.5" r="1"/><circle cx="14.5" cy="13.5" r="1"/><circle cx="9.5" cy="15" r="1"/>',
    'rights'   => '<path d="M12 3.5 5 6.2v5.2c0 4.3 2.9 7.6 7 9.1 4.1-1.5 7-4.8 7-9.1V6.2L12 3.5Z"/><path d="M9 12.2l2.2 2.2L15.5 10"/>',
    'security' => '<path d="M12 3 5 5.7v5.6c0 4.3 2.9 8.1 7 9.4 4.1-1.3 7-5.1 7-9.4V5.7L12 3Z"/>',
    'contact'  => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M4 7.5l7.1 5.1a1.7 1.7 0 0 0 2 0L20.2 7.5"/>',
];
?>

<?php $view->startBlock('title'); ?><?= $tr('common.privacy.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/privacy.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.privacy.title'),
    'subtitle' => $view->t('common.privacy.subtitle'),
]); ?>

<section class="books-panel privacy-panel">

  <!-- Оглавление: быстрый переход к разделу по якорю -->
  <nav class="privacy-toc" aria-label="<?= $tr('common.privacy.toc_title') ?>">
    <p class="privacy-toc__title"><?= $tr('common.privacy.toc_title') ?></p>
    <ul class="privacy-toc__list">
      <?php foreach ($sections as $section): ?>
        <li>
          <a class="privacy-toc__link" href="#privacy-<?= $view->e($section['id']) ?>">
            <?= $tr($section['title']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php foreach ($sections as $section): ?>
    <article class="privacy-section" id="privacy-<?= $view->e($section['id']) ?>">

      <h2 class="privacy-section__title">
        <span class="privacy-section__icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <?= $sectionIcons[$section['id']] ?? '' ?>
          </svg>
        </span>
        <?= $tr($section['title']) ?>
      </h2>

      <?php if (!empty($section['text'])): ?>
        <p class="privacy-section__text"><?= $tr($section['text']) ?></p>
      <?php endif; ?>

      <?php if (!empty($section['items'])): ?>
        <ul class="privacy-section__list">
          <?php foreach ($section['items'] as $itemKey): ?>
            <li><?= $tr($itemKey) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    </article>
  <?php endforeach; ?>

</section>

<?php $view->endBlock('content'); ?>
