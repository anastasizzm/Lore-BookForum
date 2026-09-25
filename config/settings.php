<?php

$baseDir = realpath(__DIR__ . '/..'); 

return [
    'debug' => true,
    'middleware' => [],
    'views_dir' => [
        'pages' => $baseDir . '/src/views/pages/',
        'layouts' => $baseDir . '/src/layouts',
        'partials' => $baseDir . '/src/partials/'
    ],
    'app' => [
        'namespace' => 'App',
        'path' => $baseDir . '/app/'
    ],
    'database_url' => $_ENV['DATABASE_URL'] ?? ''
];