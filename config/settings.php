<?php
declare(strict_types=1);

return [
    'debug' => true,
    'middleware' => [],
    'views_dir' => [
        'pages' => __DIR__ . '/../src/views/pages/',
        'layouts' => __DIR__ . '/../src/layouts',
        'partials' => __DIR__ . '/../src/partials/'
    ],
    'app' => [
        'namespace' => 'App',
        'path' => __DIR__ . '/../app/'
    ],
    'database_url' => $_ENV['DATABASE_URL'] ?? ''
];