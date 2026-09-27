<?php
declare(strict_types=1);

use App\Http\RouteRegistry;
use App\Http\UrlGenerator;
use App\Http\RouteUrlGenerator;

use App\Lib\Settings;
use App\Lib\Database;
use App\Lib\Container;
use App\Lib\Jwt;
use App\Lib\Auth\PolicyRegistry;

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
$container->instance(RouteRegistry::class, new RouteRegistry());
$container->singleton(UrlGenerator::class, fn(Container $c) => new RouteUrlGenerator($c->get(RouteRegistry::class)));

//Repositories
$container->singleton(UsersRepository::class);
$container->singleton(ProfilesRepository::class);

//Services
$container->instance(CookieService::class, new CookieService());
$container->singleton(AuthorizationService::class);
$container->singleton(UnitOfWork::class, fn(Container $c) => new DatabaseUnitOfWork($c->get(Database::class)));
$container->singleton(Mailer::class, fn(Container $c) => new SmtpMailer($settings));
$container->singleton(EmailVerificationService::class);
$container->singleton(AuthService::class);