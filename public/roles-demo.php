<?php
/**
 * roles-demo.php — автономный мок-стенд модуля «назначение ролей»
 * (src/partials/roles-module.php + public/assets/js/roles-module.js).
 *
 * ЗАЧЕМ: протестировать UI всех четырёх экранов, drag & drop порядка
 * ролей и вставку «+» БЕЗ бэкенда и БЕЗ сессии. Модуль открывается в
 * вынужденном мок-режиме ( open({ mock: true }) ): никаких сетевых
 * запросов, данные — встроенные MOCK из roles-module.js, правки живут
 * только в этом окне (до перезагрузки).
 *
 * КАК ЗАПУСТИТЬ (php -S — самый простой путь):
 *     php -S localhost:8000 -t public
 *     открыть http://localhost:8000/roles-demo.php
 *   (файл лежит в docroot и исполняется напрямую, мимо public/index.php
 *    и роутера; nginx+php-fpm отдаст его как существующий .php-файл).
 *
 * ЧТО ПОВТОРЯЕТ (без дублирования разметки):
 *   - реальный partial roles-module.php (через мини-шим $view);
 *   - реальный Translator (app/Lib/I18n/Translator) — те же строки, что
 *     и в проде: словари resources/lang/{locale}/common.json + errors.json;
 *   - реальные роли-module.css / i18n.js / messages.js / modal.js.
 *
 * Переключатель EN/RU — в ?lang=; переключатель режима модуля
 * (publish | roles) — кнопки над стендом.
 */

declare(strict_types=1);

use App\Lib\I18n\Translator;

// Автолоader приложения — даёт класс App\Lib\I18n\Translator
require __DIR__ . '/../vendor/autoload.php';

// --- локаль (?lang=ru) ---
$available = ['en', 'ru'];
$fallback  = 'en';
$locale    = $fallback;
if (isset($_GET['lang']) && is_string($_GET['lang'])) {
    $cand = strtolower($_GET['lang']);
    if (in_array($cand, $available, true)) {
        $locale = $cand;
    }
}

$baseDir = dirname(__DIR__);
$readJson = static function (string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
};

// Рекурсивный мерж: недостающие ключи берём из fallback-локали.
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

$langDir      = $baseDir . '/resources/lang';
$common       = $readJson($langDir . '/' . $locale . '/common.json');
if ($locale !== $fallback) {
    $common = $merge($common, $readJson($langDir . '/' . $fallback . '/common.json'));
}
$errors       = $readJson($langDir . '/' . $locale . '/errors.json');
if ($locale !== $fallback) {
    $errors = $merge($errors, $readJson($langDir . '/' . $fallback . '/errors.json'));
}
$dict         = $common;
$dict['errors'] = $errors;

// Реальный Translator — те же правила splitKey/interpolate, что в проде.
$translator = new Translator(
    langPath: $langDir,
    fallback: $fallback,
    available: $available,
    initialLocale: $locale,
);

// Мини-шим $view: partial использует только $view->e() и $view->t().
$view = new class($translator) {
    public function __construct(private Translator $translator)
    {
    }

    public function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function t(string $key, array $params = []): string
    {
        return $this->translator->t($key, $params);
    }
};

// Реальный partial (ожидает $view в области видимости).
$moduleHtml = (static function () use ($view, $baseDir): string {
    ob_start();
    require $baseDir . '/src/partials/roles-module.php';
    return (string) ob_get_clean();
})();

$asset = static fn(string $path): string => '/assets/' . ltrim($path, '/');

$payload = json_encode(
    $dict,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
);
?>
<!DOCTYPE html>
<html lang="<?= $view->e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Roles module — mock demo</title>

    <!-- Словари для JS (окно LoreI18n читает LORE_I18N) -->
    <script>
        window.LORE_I18N   = <?= $payload ?>;
        window.LORE_LOCALE = <?= json_encode($locale, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <link rel="stylesheet" href="<?= $view->e($asset('css/variable.css')) ?>">
    <link rel="stylesheet" href="<?= $view->e($asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= $view->e($asset('css/component.css')) ?>">
    <link rel="stylesheet" href="<?= $view->e($asset('css/modal.css')) ?>">
    <link rel="stylesheet" href="<?= $view->e($asset('css/roles-module.css')) ?>">

    <style>
        body { background: var(--color-bg, #EEF2F8); }
        .demo-wrap {
            max-width: 720px;
            margin: 24px auto;
            padding: 0 16px 48px;
        }
        .demo-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .demo-bar__group { display: inline-flex; gap: 4px; align-items: center; }
        .demo-bar a { color: var(--color-accent); }
        /* Карточка-«диалог», имитирующая publish-плашку */
        .demo-dialog {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #E2E8F0);
            border-radius: var(--radius-lg, 16px);
            box-shadow: var(--shadow-card, 0 4px 6px rgba(0,0,0,0.06));
            padding: 20px 22px;
        }
        .demo-dialog h1 { font-size: 18px; margin: 0 0 4px; }
        .demo-dialog .demo-sub { color: var(--color-text-muted, #6F84A5); font-size: 13px; margin: 0 0 8px; }
    </style>
</head>
<body>
<div class="demo-wrap">

    <div class="demo-bar">
        <strong>Roles module — mock demo</strong>

        <span class="demo-bar__group">
            Mode:
            <button type="button" class="btn btn--secondary" data-demo-mode="publish" disabled>Publish</button>
            <button type="button" class="btn btn--secondary" data-demo-mode="roles">Roles</button>
        </span>

        <span class="demo-bar__group">
            Lang:
            <a href="?lang=en<?= $locale === 'en' ? '' : '' ?>">EN</a> /
            <a href="?lang=ru">RU</a>
        </span>

        <button type="button" class="btn btn--secondary" data-demo-reopen>Reopen</button>
    </div>

    <div class="demo-dialog">
        <h1>Book / Article — publish</h1>
        <p class="demo-sub">Stand-in for rights-modal / article-rights-dialog. The roles module drives it.</p>

        <!-- Реальный partial модуля (корень [data-roles-module]) -->
        <?= $moduleHtml ?>
    </div>

</div>

<script src="<?= $view->e($asset('js/i18n.js')) ?>"></script>
<script src="<?= $view->e($asset('js/messages.js')) ?>"></script>
<script src="<?= $view->e($asset('js/modal.js')) ?>"></script>
<script src="<?= $view->e($asset('js/roles-module.js')) ?>"></script>
<script>
    (function () {
        'use strict';

        var root  = document.querySelector('[data-roles-module]');
        var mode  = 'publish';
        var btns  = Array.prototype.slice.call(document.querySelectorAll('[data-demo-mode]'));

        function notify(text, type) {
            if (window.Messages) window.Messages.show(text, { type: type || 'info' });
        }

        function paintMode() {
            btns.forEach(function (b) {
                var active = b.getAttribute('data-demo-mode') === mode;
                // активная кнопка — disabled (текущий режим), остальные кликабельны
                b.disabled = active;
            });
        }

        function open() {
            if (!root || !window.LoreRoles) return;
            window.LoreRoles.open(root, {
                publicationId: 1,
                mock: true,                 // <-- мок: без сети, встроенные данные
                mode: mode,
                // Publish экрана 2: в моке просто эмулируем действие хоста
                onPublish: function (done) {
                    notify(mode === 'roles'
                        ? 'Roles saved (mock).'
                        : 'Published (mock).', 'success');
                    done();
                },
                // Стрелка «назад» модуля
                onCancel: function () {
                    notify('Cancel (mock).', 'info');
                }
            });
        }

        btns.forEach(function (b) {
            b.addEventListener('click', function () {
                mode = b.getAttribute('data-demo-mode');
                paintMode();
                open();                     // переоткрыть в новом режиме
            });
        });

        var reopen = document.querySelector('[data-demo-reopen]');
        if (reopen) reopen.addEventListener('click', open);

        paintMode();
        open();
    })();
</script>
</body>
</html>
