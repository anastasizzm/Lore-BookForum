<?php
declare(strict_types=1);

namespace App\Lib;

final class Settings
{
    // --- view ---
    public readonly string $pagesPath;
    public readonly string $layoutsPath;
    public readonly string $partialsPath;
    public readonly string $assetsUrl;
    public readonly string $publicPath;

    // --- app ---
    public readonly bool   $debug;
    public readonly array  $middleware;
    public readonly string $appUrl;

    // --- i18n ---
    public readonly string $defaultLocale;
    public readonly array  $availableLocales;
    public readonly string $langPath;

    // --- database ---
    public readonly string $dbUrl;

    // --- redis ---
    public readonly string $redisHost;
    public readonly int    $redisPort;
    public readonly string $redisPassword;
    public readonly int    $redisDatabase;
    public readonly int    $redisTtl;

    // --- jwt ---
    public readonly string $jwtSecret;
    public readonly string $jwtIssuer;
    public readonly int    $jwtAccessTtl;
    public readonly int    $jwtRefreshTtl;

    // --- smtp ---
    public readonly string $mailHost;
    public readonly int    $mailPort;
    public readonly string $mailUsername;
    public readonly string $mailPassword;
    public readonly string $mailEncryption;   // 'tls' | 'ssl' | ''
    public readonly string $mailFromAddress;
    public readonly string $mailFromName;

    public function __construct(array $data)
    {
        // view
        $this->pagesPath    = rtrim($data['views_dir']['pages'] ?? '/pages', '/');
        $this->layoutsPath  = rtrim($data['views_dir']['layouts'] ?? '/layouts', '/');
        $this->partialsPath = rtrim($data['views_dir']['partials'] ?? '/partials', '/');
        $this->assetsUrl  = rtrim($data['assets_url']  ?? '/assets', '/');
        $this->publicPath = rtrim($data['public_dir'] ?? __DIR__ . '/../public', '/\\');

        // app
        $this->debug      = $data['debug'] ?? false;
        $this->middleware = $data['middleware'] ?? [];
        $this->appUrl     = $data['appUrl'] ?? 'http://localhost:8080';

        // i18n
        $this->defaultLocale    = $data['i18n']['default']     ?? 'en';
        $this->availableLocales = $data['i18n']['available']   ?? ['en'];
        $this->langPath         = rtrim($data['i18n']['path'] ?? __DIR__ . '/../resources/lang', '/\\');

        if (!in_array($this->defaultLocale, $this->availableLocales, true)) {
            throw new \RuntimeException(
                "Default locale '{$this->defaultLocale}' must be in available list"
            );
        }

        // database
        $this->dbUrl = $data['database_url'];

        // cache
        $redis = $data['redis'] ?? [];

        $this->redisHost     = $redis['host']     ?? 'redis';
        $this->redisPort     = (int) ($redis['port']     ?? 6379);
        $this->redisPassword = $redis['password'] ?? '';
        $this->redisDatabase = (int) ($redis['database'] ?? 0);
        $this->redisTtl      = (int) ($redis['ttl']      ?? 300);

        // jwt
        $this->jwtIssuer     = $data['jwt']['issuer'] ?? 'myapp';
        $this->jwtSecret     = $data['jwt']['secret'];
        $this->jwtAccessTtl  = $data['jwt']['access_ttl']  ?? 3600;
        $this->jwtRefreshTtl = $data['jwt']['refresh_ttl'] ?? 60 * 60 * 24 * 30;

        // smtp
        $mail = $data['mail'] ?? [];

        $this->mailHost       = $mail['host']       ?? 'localhost';
        $this->mailPort       = (int) ($mail['port'] ?? 587);
        $this->mailUsername   = $mail['username']   ?? '';
        $this->mailPassword   = $mail['password']   ?? '';
        $this->mailEncryption = $mail['encryption'] ?? 'tls';   // 'tls' | 'ssl' | ''
        $this->mailFromAddress = $mail['from']['address'] ?? 'noreply@localhost';
        $this->mailFromName    = $mail['from']['name']    ?? 'MyApp';
    }
}