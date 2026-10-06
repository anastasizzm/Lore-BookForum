<?php
declare(strict_types=1);

use App\Http\RouteRegistry;
use App\Http\UrlGenerator;
use App\Http\RouteUrlGenerator;

use App\Lib\Settings;
use App\Lib\Data\Database;
use App\Lib\Data\RedisClient;
use App\Lib\Container;
use App\Lib\Jwt;
use App\Lib\Auth\PolicyRegistry;

use App\Cache\User\UserContextCache;
use App\Cache\User\RedisUserContextCache;

use App\Cache\Auth\PassResetCache;
use App\Cache\Auth\RedisPassResetCache;

use App\Cache\Auth\TokenResetTtlCache;
use App\Cache\Auth\RedisTokenResetTtlCache;

use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;
use App\Services\Auth\EmailVerificationService;
use App\Services\Configuration\CookieService;
use App\Services\Configuration\UnitOfWork;
use App\Services\Configuration\DatabaseUnitOfWork;
use App\Services\Mail\Mailer;
use App\Services\Mail\SmtpMailer;

use App\Repositories\Users\UsersRepository;
use App\Repositories\Users\ProfilesRepository;


// Policies
$registry = new PolicyRegistry();
require __DIR__ . '/policies.php';


//Basics
$container->instance(PolicyRegistry::class, $registry);
$container->instance(Settings::class, $settings);
$container->instance(Jwt::class, new Jwt($settings));
$container->instance(Database::class, new Database($settings));
$container->instance(RedisClient::class, new RedisClient($settings));
$container->instance(RouteRegistry::class, new RouteRegistry());
$container->singleton(UrlGenerator::class, fn(Container $c) => new RouteUrlGenerator($c->get(RouteRegistry::class)));

//Services
$container->instance(CookieService::class, new CookieService(secureByDefault: false));
$container->singleton(AuthorizationService::class, fn(Container $c) => new AuthorizationService($c->get(PolicyRegistry::class), $c));
$container->singleton(UnitOfWork::class, fn(Container $c) => new DatabaseUnitOfWork($c->get(Database::class)));
$container->singleton(Mailer::class, fn(Container $c) => new SmtpMailer($settings));

//Model-based
$container->singleton(UserContextCache::class, fn(Container $c) => new RedisUserContextCache($c->get(RedisClient::class)));
$container->singleton(PassResetCache::class, fn(Container $c) => new RedisPassResetCache($c->get(RedisClient::class)));
$container->singleton(TokenResetTtlCache::class, fn(Container $c) => new RedisTokenResetTtlCache($c->get(RedisClient::class)));