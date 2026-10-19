<?php
declare(strict_types=1);

use App\Http\RouteRegistry;
use App\Http\UrlGenerator;
use App\Http\RouteUrlGenerator;

use App\Lib\Settings\Settings;
use App\Lib\Settings\I18nSettings;
use App\Lib\Settings\JwtSettings;
use App\Lib\Settings\MailSettings;
use App\Lib\Settings\RedisSettings;
use App\Lib\Settings\StorageSettings;
use App\Lib\Settings\ViewsSettings;

use App\Lib\Data\Database;
use App\Lib\Data\RedisClient;
use App\Lib\Container;
use App\Lib\Jwt;
use App\Lib\I18n\Translator;
use App\Lib\Auth\PolicyRegistry;

use App\Cache\User\UserContextCache;
use App\Cache\User\RedisUserContextCache;

use App\Cache\Auth\PassResetCache;
use App\Cache\Auth\RedisPassResetCache;

use App\Cache\Auth\TokenResetTtlCache;
use App\Cache\Auth\RedisTokenResetTtlCache;

use App\Cache\Auth\MailVerifyCache;
use App\Cache\Auth\RedisMailVerifyCache;

use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;
use App\Services\Auth\EmailVerificationService;
use App\Services\Configuration\CookieService;
use App\Services\Configuration\UnitOfWork;
use App\Services\Configuration\DatabaseUnitOfWork;
use App\Services\Access\AccessService;
use App\Services\Mail\Mailer;
use App\Services\Mail\SmtpMailer;

use App\Storage\FileStorage;
use App\Storage\LocalFileStorage;

use App\Repositories\Users\UsersRepository;
use App\Repositories\Users\ProfilesRepository;
use App\Repositories\RuleProviders\Concrete\RuleProvider;
use App\Repositories\RuleProviders\Concrete\PublicationRuleProvider;
use App\Repositories\RuleProviders\Concrete\PostRuleProvider;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Repositories\RuleProviders\Common\PostRuleProvider as CommonPostRuleProvider;
use App\Repositories\RuleProviders\Common\PublicationRuleProvider as CommonPublicationRuleProvider;
use App\Repositories\RuleProviders\Common\SystemRuleProvider;


// Policies
$registry = new PolicyRegistry();
require __DIR__ . '/policies.php';

//Settings
$container->instance(I18nSettings::class, $settings->i18n);
$container->instance(JwtSettings::class, $settings->jwt);
$container->instance(MailSettings::class, $settings->mail);
$container->instance(RedisSettings::class, $settings->redis);
$container->instance(StorageSettings::class, $settings->storage);
$container->instance(ViewsSettings::class, $settings->views);
$container->instance(Settings::class, $settings);

//Basics
$container->instance(PolicyRegistry::class, $registry);
$container->instance(Jwt::class, new Jwt($settings->jwt));
$container->instance(Database::class, new Database($settings));
$container->instance(RedisClient::class, new RedisClient($settings->redis));
$container->instance(RouteRegistry::class, new RouteRegistry());
$container->singleton(UrlGenerator::class, fn(Container $c) => new RouteUrlGenerator(
    $c->get(RouteRegistry::class),
    $settings,
    $c->get(Translator::class)));
$container->instance(Translator::class, new Translator(
    langPath: $settings->i18n->path,
    fallback: $settings->i18n->default,
    available: $settings->i18n->available
));

//Services
$container->instance(CookieService::class, new CookieService(secureByDefault: false));
$container->instance(FileStorage::class, new LocalFileStorage($settings->storage));
$container->singleton(AuthorizationService::class, fn(Container $c) => new AuthorizationService($c->get(PolicyRegistry::class), $c));
$container->singleton(UnitOfWork::class, fn(Container $c) => new DatabaseUnitOfWork($c->get(Database::class)));
$container->singleton(Mailer::class, fn(Container $c) => new SmtpMailer($settings));

//Model-based
$container->singleton(UserContextCache::class, fn(Container $c) => new RedisUserContextCache($c->get(RedisClient::class)));
$container->singleton(PassResetCache::class, fn(Container $c) => new RedisPassResetCache($c->get(RedisClient::class)));
$container->singleton(TokenResetTtlCache::class, fn(Container $c) => new RedisTokenResetTtlCache($c->get(RedisClient::class)));
$container->singleton(MailVerifyCache::class, fn(Container $c) => new RedisMailVerifyCache($c->get(RedisClient::class)));

// Access
$publicationAccessKey = App\Policies\Access\Publication\PublicationAccessRequirement::CONTAINER_KEY;
$postAccessKey = App\Policies\Access\Post\PostAccessRequirement::CONTAINER_KEY;
$systemAccessKey = App\Policies\Access\System\SystemAccessRequirement::CONTAINER_KEY;

$container->singletonKeyed($publicationAccessKey, RuleProvider::class, function (Container $c, string $key){
    $db = $c->get(Database::class);
    return new PublicationRuleProvider($db);
});
$container->singletonKeyed($postAccessKey, RuleProvider::class, function (Container $c, string $key){
    $db = $c->get(Database::class);
    return new PostRuleProvider($db);
});

$container->singletonKeyed($postAccessKey, CommonRuleProvider::class, function (Container $c, string $key){
    $db = $c->get(Database::class);
    return new CommonPostRuleProvider($db);
});
$container->singletonKeyed($publicationAccessKey, CommonRuleProvider::class, function (Container $c, string $key){
    $db = $c->get(Database::class);
    return new CommonPublicationRuleProvider($db);
});
$container->singletonKeyed($systemAccessKey, CommonRuleProvider::class, function (Container $c, string $key){
    $db = $c->get(Database::class);
    return new SystemRuleProvider($db);
});

foreach([$publicationAccessKey, $postAccessKey, $systemAccessKey] as $key)
{
    $container->singletonKeyed($key, AccessService::Class, function (Container $c) use ($key){
        $rp = NULL;
        $crp = NULL;
        try{
            $rp = $c->getKeyed($key, RuleProvider::class);
        }
        catch(\Throwable $e){}
        try{
            $crp = $c->getKeyed($key, CommonRuleProvider::class);
        }
        catch(\Throwable $e){}
        return new AccessService(ruleProvider: $rp, commonRuleProvider: $crp);
    });
}
