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
    'database_url' => $_ENV['DATABASE_URL'] ?? '',
    'jwt' => [
        'issuer' => 'LoreIssuer',
        'secret' => $_ENV['JWT_SECRET'] ?? '',
        'access_ttl' => 7200
    ]
];