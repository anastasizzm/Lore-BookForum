<?php
declare(strict_types=1);

use App\Http\RouteRegistry;
use App\Http\UrlGenerator;
use App\Http\RouteUrlGenerator;

use App\Lib\Settings;
use App\Lib\Database;
use App\Lib\Container;
use App\Lib\Jwt;

use App\Services\AuthService;
use App\Services\CookieService;
use App\Services\UnitOfWork;
use App\Services\DatabaseUnitOfWork;

use App\Repositories\UsersRepository;
use App\Repositories\ProfilesRepository;

//Basics
$container->instance(Settings::class, $settings);
$container->instance(Database::class, new Database($settings));
$container->instance(RouteRegistry::class, new RouteRegistry());
$container->singleton(UrlGenerator::class, fn(Container $c) => new RouteUrlGenerator($c->get(RouteRegistry::class)));

//Repositories
$container->singleton(UsersRepository::class, fn(Container $c) => new UsersRepository($c->get(Database::class)));
$container->singleton(ProfilesRepository::class, fn(Container $c) => new ProfilesRepository($c->get(Database::class)));

//Services
$container->singleton(UnitOfWork::class, fn(Container $c) => new DatabaseUnitOfWork($c->get(Database::class)));
$container->instance(CookieService::class, new CookieService());
$container->instance(Jwt::class, new Jwt($settings));
$container->singleton(AuthService::class, function (Container $c)
{
    $ur = $c->get(UsersRepository::class);
    $pr = $c->get(ProfilesRepository::class);
    $uow = $c->get(UnitOfWork::class);
    $jwt = $c->get(Jwt::class);

    return new AuthService($ur, $pr, $uow, $jwt);
});