<?php
declare(strict_types=1);

namespace App\Lib;

use RuntimeException;
use Throwable;

final class View
{
    // ---- long-lived, engine-wide state ----
    private static ?Settings $engineSettings = null;
    /** @var array<string, mixed> */
    private static array $globals = [];

    // ---- per-render state ----
    /** @var array<string, string> */
    private array $blocks = [];
    private ?string $currentLayout = null;
    /** @var array<string, string> */
    private array $pathCache = [];

    private function __construct(
        private Settings $settings,
    ) {}

    // ---------- static bootstrap API ----------

    public static function configure(Settings $settings): void
    {
        foreach (
            [$settings->pagesPath, $settings->layoutsPath, $settings->partialsPath]
            as $path
        ) {
            if (!is_dir($path)) {
                throw new RuntimeException("View path does not exist: $path");
            }
        }

        self::$engineSettings = $settings;
    }

    public static function share(string $key, mixed $value): void
    {
        self::$globals[$key] = $value;
    }

    public static function render(string $page, array $data = []): string
    {
        if (self::$engineSettings === null) {
            throw new RuntimeException(
                'View engine not configured. Call View::configure() first.'
            );
        }

        return (new self(self::$engineSettings))->renderPage($page, $data);
    }

    /** For long-running servers (RoadRunner, Swoole, FrankenPHP). */
    public static function reset(): void
    {
        self::$engineSettings = null;
        self::$globals = [];
    }

    // ---------- template API (called as $view->...) ----------

    public function extends(string $layout): void
    {
        $this->currentLayout = $layout;
    }

    public function include(string $partial, array $data = []): void
    {
        echo $this->capture(
            $this->resolve($partial, 'partials', 'partial'),
            $data
        );
    }

    /** Same as include() but returns instead of echoing. */
    public function partial(string $partial, array $data = []): string
    {
        return $this->capture(
            $this->resolve($partial, 'partials', 'partial'),
            $data
        );
    }

    /** Returns block content. Use <?= $view->block('name') ?> in layouts. */
    public function block(string $name): string
    {
        return $this->blocks[$name] ?? '';
    }

    public function startBlock(string $name): void
    {
        ob_start();
    }

    public function endBlock(string $name): void
    {
        $this->blocks[$name] = (string) ob_get_clean();
    }

    public function setBlock(string $name, string $content): void
    {
        $this->blocks[$name] = $content;
    }

    public function e(?string $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    public function csrfField(): string
    {
        $token = (string) (self::$globals['csrfToken'] ?? '');

        if ($token === '') {
            return '';
        }

        return '<input type="hidden" name="'
            . CsrfManager::FIELD
            . '" value="' . $this->e($token) . '">';
        }

    // ---------- internals ----------

    private function renderPage(string $page, array $data): string
    {
        $content = $this->capture(
            $this->resolve($page, 'pages', 'page'),
            $data
        );

        if ($this->currentLayout !== null) {
            $layout = $this->currentLayout;
            $this->currentLayout = null;

            $content = $this->capture(
                $this->resolve($layout, 'layouts', 'layout'),
                $data
            );
        }

        return $content;
    }

    private function capture(string $file, array $vars): string
    {
        $view = $this;

        extract($vars + self::$globals, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    private function resolve(string $name, string $type, string $label): string
    {
        // Block traversal: "../", "..\", "/..", etc.
        if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $name)) {
            throw new RuntimeException("Invalid $label name: $name");
        }

        $cacheKey = "$type:$name";
        if (isset($this->pathCache[$cacheKey])) {
            return $this->pathCache[$cacheKey];
        }

        $base = match ($type) {
            'pages'    => $this->settings->pagesPath,
            'layouts'  => $this->settings->layoutsPath,
            'partials' => $this->settings->partialsPath,
            default    => throw new RuntimeException("Unknown view type: $type"),
        };

        $file = rtrim($base, '/\\') . DIRECTORY_SEPARATOR
              . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("$label not found: $name ($file)");
        }

        return $this->pathCache[$cacheKey] = $file;
    }
}