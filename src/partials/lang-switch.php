<?php
/**
 * lang-switch — переключатель языка.
 *
 * Подключается где угодно:
 *   <?php $view->include('lang-switch'); ?>
 *
 * Параметры (все опциональны):
 *   $variant — 'fab' (плавающая иконка, по умолчанию) | 'inline' (кнопка в потоке).
 *   $position — 'left' | 'right' (по умолчанию 'left'); влияет только на 'fab'.
 *   $label    — показывать ли текстовый код языка (RU/EN). По умолчанию true.
 *
 * Открывается через <details> — работает без JS.
 * Ссылки ведут на тот же URL с ?lang=xx (остальные параметры сохраняются).
 *
 * Стили — в public/assets/css/login.css (классы .lang-switch*).
 * Используется на auth-страницах, где sidebar нет.
 */

$variant   = $variant   ?? 'fab';
$position  = $position  ?? 'left';
$label     = $label     ?? true;

$curLocale = method_exists($view, 'locale') ? $view->locale() : 'en';

// Языки: код => название на самом языке. Новый язык = одна строка здесь
// + папка resources/lang/<код>/ + код в i18n.available (config).
$languages = $languages ?? ['en' => 'English', 'ru' => 'Русский'];
$curLangName = $languages[$curLocale] ?? strtoupper($curLocale);

// Строит тот же URL с ?lang=<code>, сохраняя остальные параметры.
$langUrl = static function (string $code): string {
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);
    $q['lang'] = $code;
    return $path . '?' . http_build_query($q);
};

$classes = 'lang-switch'
    . ' lang-switch--' . $view->e($variant)
    . ' lang-switch--' . $view->e($position);
?>
<details class="<?= $classes ?>"
         aria-label="<?= $view->e($view->t('common.language.switch')) ?>">

  <summary class="lang-switch__trigger"
           title="<?= $view->e($view->t('common.language.label')) ?>">
    <span class="lang-switch__icon" aria-hidden="true">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/>
        <path d="M3 12h18"/>
        <path d="M12 3c2.5 2.6 3.8 5.6 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>
      </svg>
    </span>
    <?php if ($label): ?>
      <span class="lang-switch__current"><?= $view->e(strtoupper($curLocale)) ?></span>
    <?php endif; ?>
  </summary>

  <ul class="lang-switch__list">
    <?php foreach ($languages as $code => $name): ?>
      <li>
        <a href="<?= $view->e($langUrl($code)) ?>"
           class="lang-switch__link<?= $curLocale === $code ? ' is-active' : '' ?>"
           hreflang="<?= $view->e($code) ?>" lang="<?= $view->e($code) ?>"
           <?= $curLocale === $code ? 'aria-current="true"' : '' ?>>
          <span><?= $view->e($name) ?></span>
          <?php if ($curLocale === $code): ?>
            <span class="lang-switch__check" aria-hidden="true">&#10003;</span>
          <?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</details>