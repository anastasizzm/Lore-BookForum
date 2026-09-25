<?php
declare(strict_types=1);

namespace App\Lib;

final class Settings
{
    public readonly string $pagesPath;
    public readonly string $layoutsPath;
    public readonly string $partialsPath;
    public readonly bool $debug;
    public readonly array $middleware;
    public readonly string $dbUrl;

    public function __construct(array $data)
    {
        $this->pagesPath    = $data['views_dir']['pages'];
        $this->layoutsPath  = $data['views_dir']['layouts'];
        $this->partialsPath = $data['views_dir']['partials'];
        $this->debug        = $data['debug'] ?? false;
        $this->middleware  = $data['middleware'] ?? [];
        $this->dbUrl       = $data['database_url'];
    }
}