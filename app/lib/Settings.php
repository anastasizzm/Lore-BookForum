<?php
declare(strict_types=1);

namespace App\Lib;

final class Settings
{
    // --- view ---
    public readonly string $pagesPath;
    public readonly string $layoutsPath;
    public readonly string $partialsPath;

    // --- app ---
    public readonly bool   $debug;
    public readonly array  $middleware;

    // --- database ---
    public readonly string $dbUrl;

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
        $this->pagesPath    = $data['views_dir']['pages'];
        $this->layoutsPath  = $data['views_dir']['layouts'];
        $this->partialsPath = $data['views_dir']['partials'];

        // app
        $this->debug      = $data['debug'] ?? false;
        $this->middleware = $data['middleware'] ?? [];

        // database
        $this->dbUrl = $data['database_url'];

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