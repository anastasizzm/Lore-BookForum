<?php
declare(strict_types=1);

namespace App\Lib;

use App\Lib\Settings;
use RuntimeException;

final class View
{
    private static array $globals = [];
    private static array $blocks = [];
    private static ?string $currentLayout = null;
    private static ?Settings $settings = null;

    public static function configure(Settings $settings): void
    {
        self::$settings = $settings;
    }

    public static function share(string $key, mixed $value): void { self::$globals[$key] = $value; }

    public static function render(string $page, array $data = []): string
    {
        if (self::$settings === null) {
            throw new RuntimeException("View engine not configured.");
        }

        self::$blocks = [];
        self::$currentLayout = null;

        $pageFile = self::$settings->pagesPath . $page . '.php';
        
        if (!is_file($pageFile)) {
            throw new RuntimeException("View not found: $pageFile");
        }

        $content = self::capture($pageFile, $data);

        if (self::$currentLayout) {
            $layoutFile = self::$settings->layoutsPath . self::$currentLayout . '.php';
            return self::capture($layoutFile, $data);
        }

        return $content;
    }

    // --- Template Tags ---

    public static function extends(string $layout): void { self::$currentLayout = $layout; }

    public static function include(string $partial, array $data = []): void
    {
        $path = self::$settings->partialsPath . $partial . '.php';
        echo self::capture($path, $data);
    }

    public static function block(string $name, ?string $content = null): void
    {
        if ($content !== null) {
            self::$blocks[$name] = $content;
        } else {
            echo self::$blocks[$name] ?? '';
        }
    }

    public static function startBlock(string $name): void { ob_start(); }

    public static function endBlock(string $name): void 
    { 
        self::$blocks[$name] = ob_get_clean(); 
    }

    // --- Core Logic ---

    private static function capture(string $file, array $vars): string
    {
        extract($vars + self::$globals, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function csrfField(): string
    {
        $token = \App\Lib\CsrfManager::getToken();
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}