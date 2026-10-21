<?php $view->extends('main'); ?>
<?php $tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p)); ?>

<?php
/*
 * help — статическая страница помощи (задача 5).
 *
 * Локально открыть через тест-роут (роута /help пока нет — config/routes.php
 * заморожен): /pages/test?page=help
 * На кнопку «Помощь» в сайдбаре (src/partials/sidebar.php) страница
 * открывается по той же ссылке — когда бэк заведёт роут help, заменить
 * путь в sidebar.php на $url('help'), остальное уже готово.
 *
 * Контент полностью в resources/lang/{en,ru}/common.json, секция help —
 * страница статическая, JS не нужен.
 *
 * Стили — public/assets/css/help.css (.help-*); каркас панели — общие
 * .books-panel (component.css), шапка — partial page-header.
 */

$view->setBlock('selectedTab', 'help');

/**
 * Разделы страницы: id якоря, ключи перевода заголовка и текста,
 * ключи пунктов списка (если есть). Порядок — как на странице.
 */
$sections = [
    ['id' => 'about',     'title' => 'common.help.about_title',    'text' => 'common.help.about_text'],
    ['id' => 'start',     'title' => 'common.help.start_title',    'items' => ['common.help.start_1', 'common.help.start_2', 'common.help.start_3']],
    ['id' => 'books',     'title' => 'common.help.books_title',    'items' => ['common.help.books_1', 'common.help.books_2', 'common.help.books_3', 'common.help.books_4', 'common.help.books_5']],
    ['id' => 'articles',  'title' => 'common.help.articles_title', 'items' => ['common.help.articles_1']],
    ['id' => 'drafts',    'title' => 'common.help.drafts_title',   'items' => ['common.help.drafts_1']],
    ['id' => 'saved',     'title' => 'common.help.saved_title',    'items' => ['common.help.saved_1']],
    ['id' => 'comments',  'title' => 'common.help.comments_title', 'items' => ['common.help.comments_1']],
    ['id' => 'profile',   'title' => 'common.help.profile_title',  'items' => ['common.help.profile_1']],
    ['id' => 'support',   'title' => 'common.help.support_title',  'text' => 'common.help.support_text'],
];

/** Иконка раздела: простой SVG в стиле иконок сайдбара (currentColor). */
$sectionIcons = [
    'about'    => '<path d="M12 21.5a9.5 9.5 0 1 0 0-19 9.5 9.5 0 0 0 0 19Z"/><path d="M12 11v5"/><path d="M12 7.6v.2"/>',
    'start'    => '<path d="M13 2 4.5 13.5H11l-1 8.5 9-11.5h-6.5L13 2Z"/>',
    'books'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5v-15Z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H19v3H6.5A2.5 2.5 0 0 1 4 20.5Z"/>',
    'articles' => '<path d="M5 3h10l4 4v14H5V3Z"/><path d="M15 3v4h4"/><path d="M8.5 12h7M8.5 15.5h7M8.5 19h4"/>',
    'drafts'   => '<path d="M6 3h8l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4"/><path d="M8 12h8M8 15.5h8M8 19h5"/>',
    'saved'    => '<path d="M6 3h12v18l-6-4.5L6 21V3Z"/>',
    'comments' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H9l-5 4V5.5Z"/>',
    'profile'  => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
    'support'  => '<path d="M4 12a8 8 0 1 1 16 0v5a2 2 0 0 1-2 2h-2v-6h4M4 13h4v6H6a2 2 0 0 1-2-2v-5Z"/>',
];
?>

<?php $view->startBlock('title'); ?><?= $tr('common.help.title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/help.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.help.title'),
    'subtitle' => $view->t('common.help.subtitle'),
]); ?>

<section class="books-panel help-panel">

  <!-- Оглавление: быстрый переход к разделу по якорю -->
  <nav class="help-toc" aria-label="<?= $tr('common.help.toc_title') ?>">
    <p class="help-toc__title"><?= $tr('common.help.toc_title') ?></p>
    <ul class="help-toc__list">
      <?php foreach ($sections as $section): ?>
        <li>
          <a class="help-toc__link" href="#help-<?= $view->e($section['id']) ?>">
            <?= $tr($section['title']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php foreach ($sections as $section): ?>
    <article class="help-section" id="help-<?= $view->e($section['id']) ?>">

      <h2 class="help-section__title">
        <span class="help-section__icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <?= $sectionIcons[$section['id']] ?? '' ?>
          </svg>
        </span>
        <?= $tr($section['title']) ?>
      </h2>

      <?php if (!empty($section['text'])): ?>
        <p class="help-section__text"><?= $tr($section['text']) ?></p>
      <?php endif; ?>

      <?php if (!empty($section['items'])): ?>
        <ul class="help-section__list">
          <?php foreach ($section['items'] as $itemKey): ?>
            <li><?= $tr($itemKey) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    </article>
  <?php endforeach; ?>

</section>

<?php $view->endBlock('content'); ?>
