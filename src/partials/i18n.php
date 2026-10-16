<?php
/**
 * i18n — выводит словари текущей локали в JS.
 *
 * Подключается в head.php ПЕРВЫМ (до i18n.js и остальных скриптов):
 *   <?php $view->include('i18n'); ?>
 *
 * Кладёт в window:
 *   LORE_I18N   — common.json текущей локали (+ фолбэк-мерж),
 *                 плюс errors.json под ключом `errors`
 *                 (т.е. доступно как LoreI18n.t('errors.article.…'))
 *   LORE_LOCALE — код текущей локали (для ?lang= и formatDate)
 *
 * Локаль: сначала ?lang=, потом $view->locale() (сервер уже мог её вычислить),
 * потом фолбэк.
 */

// --- определяем локаль ---
$available = ['en', 'ru'];   // подстрой под свои языки
$fallback  = 'en';

$locale = null;

// 1. ?lang= из URL
if (isset($_GET['lang']) && is_string($_GET['lang'])) {
    $candidate = strtolower($_GET['lang']);
    if (in_array($candidate, $available, true)) $locale = $candidate;
}

// 2. Что уже знает View (LocaleMiddleware прочитал cookie/сессию)
if ($locale === null && method_exists($view, 'locale')) {
    $candidate = strtolower((string) $view->locale());
    if (in_array($candidate, $available, true)) $locale = $candidate;
}

// 3. Фолбэк
if ($locale === null) $locale = $fallback;

// --- путь до словарей ---
$langDir = dirname(__DIR__, 2) . '/resources/lang';

$readJson = static function (string $path): array {
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
};

// Рекурсивный мерж: ключи, которых нет в $a, берём из $b.
$merge = static function (array $a, array $b) use (&$merge): array {
    foreach ($b as $k => $v) {
        if (!array_key_exists($k, $a)) {
            $a[$k] = $v;
        } elseif (is_array($v) && is_array($a[$k])) {
            $a[$k] = $merge($a[$k], $v);
        }
    }
    return $a;
};

$localeIsFallback = ($locale === $fallback);

// --- common.json ---
$commonPrimary  = $readJson($langDir . '/' . $locale   . '/common.json');
$commonFallback = $localeIsFallback ? [] : $readJson($langDir . '/' . $fallback . '/common.json');
$common         = $merge($commonPrimary, $commonFallback);

// --- errors.json ---
$errorsPrimary  = $readJson($langDir . '/' . $locale   . '/errors.json');
$errorsFallback = $localeIsFallback ? [] : $readJson($langDir . '/' . $fallback . '/errors.json');
$errors         = $merge($errorsPrimary, $errorsFallback);

// errors.json доступен в JS по пути `errors.<раздел>.<ключ>`:
//   LoreI18n.t('errors.article.rule_article_content')
//   LoreI18n.t('errors.validation.password_min', { min: 8 })
$dict = $common;
$dict['errors'] = $errors;
?>
<script>
window.LORE_I18N   = <?= json_encode(
    $dict,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
) ?>;
window.LORE_LOCALE = <?= json_encode($locale, JSON_UNESCAPED_UNICODE) ?>;
</script>