<?php
declare(strict_types=1);

namespace App\Services\Users;

use App\Cache\User\UserContextCache;

use App\Forms\Users\ProfileForm;
use App\Attributes\Inject;

use App\Services\Configuration\UnitOfWork;
use App\Validators\Users\ProfileFormValidator;

use App\Models\PaginatedList;
use App\Models\Queries\Users\UsersListQuery;
use App\Models\Enums\ServiceType;
use App\Models\Access\Permissions\RulesCombination;
use App\Models\Users\UserContext;
use App\Models\Users\UserData;
use App\Models\Users\UserShortData;
use App\Repositories\Users\UsersRepository;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Exceptions\Translators\UserExceptionTranslator;
use App\Factories\CriteriasFactory;
use Throwable;
use PDO;

final class UsersService
{
    public function __construct(
        private readonly UserContextCache $cache,
        private readonly UnitOfWork $uow,
        private readonly UsersRepository  $usersRepo,
        private readonly UserExceptionTranslator $translator,
        private readonly ProfileFormValidator $profileFormValidator,

        #[Inject(key: ServiceType::System)]
        private readonly CommonRuleProvider $systemRuleProvider,

        #[Inject(key: ServiceType::Post)]
        private readonly CommonRuleProvider $postRuleProvider,

        #[Inject(key: ServiceType::Publication)]
        private readonly CommonRuleProvider $pubRuleProvider
    ){}

    public function getList(UsersListQuery $query) : PaginatedList 
    {
        $criteria = CriteriasFactory::buildUserFromQuery($query, UserShortData::class);
        return $this->usersRepo->getList($criteria,);
    }

    public function loadContext(int $userId): ?UserContext
    {
        $user = null;

        try {
            $user = $this->cache->get($userId);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx get failed: ' . $e->getMessage());
        }
        if ($user !== null) {
            return $user;
        }

        $user = $this->usersRepo->loadContext($userId);
        if ($user === null) {
            return null;
        }

        $sysRules = $this->systemRuleProvider->getRule($userId);
        $postRules = $this->postRuleProvider->getRule($userId);
        $pubRules = $this->pubRuleProvider->getRule($userId);

        $rulesCombination = RulesCombination::fromArray([
            'system' => $sysRules,
            'post' => $postRules,
            'publication' => $pubRules
        ]);

        $user = $user->withRules($rulesCombination);
        try {
            $this->cache->set($user, ttl: 300);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx set failed: ' . $e->getMessage());
        }

        return $user;
    }

    public function forgetContext(int $userId): void
    {
        try {
            $this->cache->forget($userId);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx forget failed: ' . $e->getMessage());
        }
    }

    public function retrieve(int $userId) : ?UserData
    {
        return $this->usersRepo->retrieve($userId);
    }

    public function editProfile(int $userId, ProfileForm $form)
    {
        $errorBag = $this->profileFormValidator->validateOne($form);
        if (!$errorBag->isEmpty()) throw new ValidationException($errorBag->all());

        try{
            $this->usersRepo->editProfile($userId, $form->name, $form->surname, $form->bio, $form->avatar);
        }
        catch(\PDOException $e){
            throw $this->translator->translate($e);
        }
    }
}