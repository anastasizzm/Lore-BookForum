<?php

$baseDir = realpath(__DIR__ . '/..'); 

return [
    'debug' => true,

    'middleware' => [
        App\Middleware\AuthMiddleware::class,
        App\Middleware\CsrfMiddleware::class
    ],

    'views_dir' => [
        'pages' => $baseDir . '/src/views/pages/',
        'layouts' => $baseDir . '/src/layouts',
        'partials' => $baseDir . '/src/partials/'
    ],

    'database_url' => $_ENV['APP_DATABASE_URL'] ?? 
        throw new RuntimeException('APP_DATABASE_URL not set'),

    'jwt' => [
        'issuer' => 'LoreIssuer',
        'secret' => $_ENV['JWT_SECRET'] ?? '',
        'access_ttl' => 7200
    ],

    'mail' => [
        'host'       => $_ENV['MAIL_HOST']       ?? 'localhost',
        'port'       => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'username'   => $_ENV['MAIL_USERNAME']   ?? '',
        'password'   => $_ENV['MAIL_PASSWORD']   ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM']      ?? 'noreply@localhost',
            'name'    => $_ENV['MAIL_FROM_NAME'] ?? 'MyApp',
        ],
    ],
];