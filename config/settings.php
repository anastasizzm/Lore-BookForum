<?php


$baseDir = realpath(__DIR__ . '/..'); 

return [
    
    'debug' => true,

    'middleware' => [
        App\Middleware\ExceptionMiddleware::class,
        App\Middleware\LocaleMiddleware::class,
        App\Middleware\JwtMiddleware::class,
        App\Middleware\TokenBlockerMiddleware::class,
        App\Middleware\CsrfMiddleware::class,
        App\Middleware\AuthorizationMiddleware::class,
        App\Middleware\ViewGlobalsMiddleware::class,
    ],

    'views' => [
        'pages' => $baseDir . '/src/views/pages',
        'layouts' => $baseDir . '/src/layouts',
        'partials' => $baseDir . '/src/partials'
    ],

    'i18n' => [
        'default' => $_ENV['APP_LOCALE'] ?? 'en',
        'available' => ['en', 'ru'],
        'path' => $baseDir . '/resources/lang'
    ],

    'appUrl' => $_ENV['APP_URL'] ?? 
        throw new RuntimeException('APP_URL not set'),

    'public_dir' => $baseDir . '/public',

    'assets_url' => '/assets',

    'database_url' => $_ENV['APP_DATABASE_URL'] ?? 
        throw new RuntimeException('APP_DATABASE_URL not set'),

    'redis' => [
        'host'     => $_ENV['REDIS_HOST']     ?? 'redis',
        'port'     => (int) ($_ENV['REDIS_PORT']     ?? 6379),
        'password' => $_ENV['REDIS_PASSWORD'] ?? '',
        'database' => (int) ($_ENV['REDIS_DB']       ?? 0),
        'ttl'      => (int) ($_ENV['REDIS_TTL']      ?? 300),
    ],

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

    'storage' => [
        'path' => $baseDir . '/storage/uploads',
        'allowed' => [
            'cover' => [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ],
            'book' => [
                'application/pdf'      => 'pdf',
                'application/epub+zip' => 'epub',
                'application/x-mobipocket-ebook' => 'mobi',
                'application/x-fictionbook+xml'  => 'fb2',
            ],
        ],
        'max_sizes' => [
            'cover'  => 5 * 1024 * 1024, // 5 МБ
            'book'   => 100 * 1024 * 1024, // 100 МБ
        ],
        'internal_url_prefix' => '/files',
        'use_x_accel' => true
    ]
];