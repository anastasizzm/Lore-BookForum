<?php

$baseDir = realpath(__DIR__ . '/..'); 

return [
    'debug' => true,

    'middleware' => [
        App\Middleware\ExceptionMiddleware::class,
        App\Middleware\AuthMiddleware::class,
        App\Middleware\CsrfMiddleware::class,
        App\Middleware\AuthorizationMiddleware::class,
        App\Middleware\ViewGlobalsMiddleware::class,
    ],

    'views_dir' => [
        'pages' => $baseDir . '/src/views/pages',
        'layouts' => $baseDir . '/src/layouts',
        'partials' => $baseDir . '/src/partials'
    ],

    'public_dir' => $baseDir . '/public',

    'assets_url' => '/assets',

    'database_url' => $_ENV['APP_DATABASE_URL'] ?? 
        throw new RuntimeException('APP_DATABASE_URL not set'),

    'jwt' => [
        'issuer' => 'LoreIssuer',
        'secret' => $_ENV['JWT_SECRET'] ?? 
            throw new RuntimeException('JWT_SECRET not set'),
        'access_ttl' => 7200
    ],

    'mail' => [
        'host'       => $_ENV['MAIL_HOST']       ?? 'localhost',
        'port'       => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'username'   => $_ENV['MAIL_USERNAME']   ?? 
            throw new RuntimeException('MAIL_USERNAME not set'),
        'password'   => $_ENV['MAIL_PASSWORD']   ?? 
            throw new RuntimeException('MAIL_PASSWORD not set'),
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM']      ?? 'noreply@localhost',
            'name'    => $_ENV['MAIL_FROM_NAME'] ?? 'Lore',
        ],
    ],
];