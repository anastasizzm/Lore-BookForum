<?php
/**
 * fab — плавающая кнопка «+» в правом нижнем углу: меню «Создать статью / Создать книгу».
 *
 * Подключается ОДИН раз в layouts/main.php (после confirm-modal):
 *   <?php $view->include('fab'); ?>
 * Логика — public/assets/js/fab.js, стили — public/assets/css/fab.css.
 *
 * Показывается только подтверждённым пользователям ($is_verified — общий глобал из
 * ViewGlobalsMiddleware): оба маршрута создания требуют AuthPolicy::Verified.
 *
 * Скрыть на странице (там, где правый нижний угол занят: читалка, формы создания):
 *   <?php $view->setBlock('hideFab', '1'); ?>
 * Запасной вариант без правки страницы — data-hide-on ниже (fab.js прячет кнопку,
 * если на странице есть такой элемент).
 *
 * Ссылки: книга — $view->url('books.create'); статья — /articles/create
 * (маршрута в config/routes.php пока нет — его добавляет бэк).
 */
if (empty($is_verified) || !empty($view->block('hideFab'))) {
    return;
}

$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ?lang= во всех ссылках — как в сайдбаре, чтобы язык не слетал
$fabLang = isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== ''
    ? $_GET['lang']
    : $view->locale();
$fabUrl = static function (string $base) use ($fabLang): string {
    if ($fabLang === null || $fabLang === '') return $base;
    return $base . (str_contains($base, '?') ? '&' : '?') . 'lang=' . urlencode((string) $fabLang);
};

$fabItems = [
    [
        'href'  => $fabUrl('/articles/create'),
        'label' => $tr('common.fab.article'),
        'icon'  => '<path d="M5 2.5h7l3 3v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M7.5 9h5M7.5 12h5M7.5 15h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
    ],
    [
        'href'  => $fabUrl($view->url('books.create')),
        'label' => $tr('common.fab.book'),
        'icon'  => '<path d="M4 3.5A1.5 1.5 0 0 1 5.5 2H15v13H5.5A1.5 1.5 0 0 0 4 16.5v-13z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M4 16.5A1.5 1.5 0 0 0 5.5 18H15v-3" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 6h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
    ],
];
?>
<div class="fab" data-fab data-hide-on="[data-reader], [data-article-form], [data-book-form]">
  <div class="fab__menu" id="fabMenu" data-fab-menu role="menu" aria-label="<?= $tr('common.fab.create') ?>" hidden>
    <?php foreach ($fabItems as $item): ?>
      <a class="fab__item" role="menuitem" href="<?= $view->e($item['href']) ?>">
        <span class="fab__item-icon">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><?= $item['icon'] ?></svg>
        </span>
        <span><?= $item['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <button type="button"
          class="fab__toggle"
          data-fab-toggle
          aria-haspopup="menu"
          aria-expanded="false"
          aria-controls="fabMenu"
          aria-label="<?= $tr('common.fab.create') ?>">
    <svg class="fab__plus" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
    </svg>
  </button>
</div>