<?php
declare(strict_types=1);

use App\Lib\Settings;
use App\Lib\Database;
use App\Lib\Container;
use App\Lib\Jwt;

use App\Services\AuthService;
use App\Services\CookieService;

use App\Repositories\UsersRepository;

$container->instance(Settings::class, $settings);
$container->instance(Database::class, new Database($settings));

$container->singleton(UsersRepository::class, fn(Container $c) => new UsersRepository($c->get(Database::class)));

$container->instance(CookieService::class, new CookieService());
$container->instance(Jwt::class, new Jwt($settings));
$container->singleton(AuthService::class, 
    fn(Container $c) => new AuthService($c->get(UserRepository::class, $c->get(Jwt::class)))
);