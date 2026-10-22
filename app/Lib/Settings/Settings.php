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
    public int $defaultRoleId;
    
    public string $appUrl;
    public string $assetsUrl;
    public string $publicPath;
    public string $dbUrl;

    public function __construct(array $data)
    {
        $this->views = ViewsSettings::fromData($data);
        $this->redis = RedisSettings::fromData($data);
        $this->i18n = I18nSettings::fromData($data);
        $this->jwt = JwtSettings::fromData($data);
        $this->mail = MailSettings::fromData($data);
        $this->storage = StorageSettings::fromData($data);
        
        $this->debug      = $data['debug'] ?? false;
        $this->defaultRoleid = $data['defaultRoleId'] ?? 0;
        $this->middleware = $data['middleware'] ?? [];
        $this->appUrl     = $data['appUrl'] ?? 'http://localhost:8080';
        $this->assetsUrl  = rtrim($data['assets_url']  ?? '/assets', '/');
        $this->publicPath = rtrim($data['public_dir'] ?? __DIR__ . '/../public', '/\\');
        $this->dbUrl = $data['database_url'];
    }
}