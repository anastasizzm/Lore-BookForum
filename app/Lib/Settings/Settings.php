<?php
declare(strict_types=1);

namespace App\Lib\Settings  ;

final readonly class Settings
{
    public ViewsSettings $views;
    public RedisSettings $redis;
    public I18nSettings $i18n;
    public JwtSettings $jwt;
    public MailSettings $mail;
    public StorageSettings $storage;

    public bool $debug;
    public array $middleware;
    
    public string $appUrl;
    public string $assetsUrl;
    public string $publicPath;
    public string $dbUrl;

    public function __construct(array $data)
    {
        $views = $data['views_dir'];
        $this->views = new ViewsSettings(
            pages: rtrim($views['pages'] ?? '/pages', '/'),
            layouts: rtrim($views['layouts'] ?? '/layouts', '/'),
            partials: rtrim($views['partials'] ?? '/partials', '/')
        );

        $redis = $data['redis'] ?? [];
        $this->redis = new RedisSettings(
            host: $redix['host'] ?? 'redis',
            port: (int)($redis['port'] ?? 6379),
            password: $redis['password'] ?? '',
            database: (int)($redis['database'] ?? 0),
            ttl: (int)($redis['ttl'] ?? 300)
        );

        $i18n = $data['i18n'] ?? [];
        $this->i18n = new I18nSettings(
            default: $i18n['default'] ?? 'en',
            available: $i18n['available'] ?? ['en'],
            path: rtrim($i18n['path'] ?? __DIR__ . '/resources/lang', '/\\')
        );

        $jwt = $data['jwt'] ?? [];
        $this->jwt = new JwtSettings(
            issuer: $jwt['issuer'] ?? 'myapp',
            secret: $jwt['secret'],
            accessTtl: $jwt['access_ttl']  ?? 3600,
            refreshTtl: $jwt['refresh_ttl']  ?? 60 * 60 * 24 * 30
        );

        $mail = $data['mail'] ?? [];
        $this->mail = new MailSettings(
            host: $mail['host'] ?? 'localhost',
            port: (int)($mail['port'] ?? 587),
            username: $mail['username'] ?? '',
            password: $mail['password'] ?? '',
            encryption: $mail['encryption'] ?? 'tls',
            from: new MailOwner(
                address: $mail['from']['address'] ?? 'noreply@localhost',
                name: $mail['from']['name'] ?? 'MyApp'
            )
        );

        // $this->storage = new StorageSettings(

        // );
        
        $this->debug      = $data['debug'] ?? false;
        $this->middleware = $data['middleware'] ?? [];
        
        $this->appUrl     = $data['appUrl'] ?? 'http://localhost:8080';
        $this->assetsUrl  = rtrim($data['assets_url']  ?? '/assets', '/');
        $this->publicPath = rtrim($data['public_dir'] ?? __DIR__ . '/../public', '/\\');
        $this->dbUrl = $data['database_url'];
    }
}