<?php
declare(strict_types=1);

namespace App\Controllers\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Auth\AccountService;
use App\Services\Configuration\CookieService;
use App\Services\Publications\BooksService;
use App\Services\Publications\ArticlesService;

use App\Http\HttpContext;
use App\Http\HttpException;
use App\Http\Response;
use App\Http\UrlGenerator;

use App\Lib\I18n\Translator;

use App\Models\Queries\Publications\BooksListQuery;
use App\Models\Queries\Publications\ArticlesListQuery;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Filters\Publications\BooksFilters;
use App\Models\Filters\Publications\ArticlesFilters;
use App\Models\Filters\Publications\UserRelationFilters;
use App\Extensions\Parsers\RouteParamParser;
use App\Extensions\Parsers\QueryParser;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Forms\Auth\AccountCreditsForm;
use App\Forms\Users\ProfileForm;

use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly AccountService $accountService,
        private readonly BooksService $booksService,
        private readonly ArticlesService $articlesService,
        private readonly CookieService $cookies,
        private readonly Translator $translator,
        private readonly UrlGenerator $url
    ){}

    public function retrieve(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->renderNotFound();

        return $this->render('profile/profile', ['user' => $userContext, 'userData' => $userData]);
    }

    public function getEdit(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->renderNotFound();

        return $this->render('profile/profile-edit', ['user' => $userContext, 'userData' => $userData]);
    }

    public function postEdit(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $formData = $context->request->body();

        try {
            $this->usersService->editProfile($userId, ProfileForm::fromInput($formData));
        } catch (ValidationException $e) {
            return $this->renderProfileEdit($currentUserId, $userId, $formData, $e->errors(), 422);
        }

        return Response::redirect($this->url->url('users.profile', ['userId' => $userId]));
    }

    public function postCredits(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $formData = $context->request->body();

        try {
            $token = $this->accountService->changeCredits($userId, AccountCreditsForm::fromInput($formData));

            if ($token !== null) {
                $response = $this->render('message', [
                    'statusCode'   => 202,
                    'message'      => $this->translator->t('errors.mail.verify_mail'),
                    'actionUrl'    => $this->url->url('users.profile', ['userId' => $userId]),
                    'actionTitle'  => $this->translator->t('common.profile.back_to'),
                ], 202);

                return $this->cookies->set($response, Constants::TOKEN_COOKIE, $token);
            }
        } catch (ValidationException $e) {
            return $this->renderProfileEdit($currentUserId, $userId, $formData, $e->errors(), 422);
        } catch (HttpException $e) {
            return $this->render('message', [
                'statusCode'   => $e->getStatus(),
                'message'      => $e->getMessage(),
                'actionUrl'    => $this->url->url('users.profile', ['userId' => $userId]),
                'actionTitle'  => $this->translator->t('common.profile.back_to'),
            ], $e->getStatus());
        }

        return Response::redirect($this->url->url('users.profile', ['userId' => $userId]));
    }

    private function renderProfileEdit(
        int $currentUserId,
        int $userId,
        array $form,
        array $errors,
        int $statusCode
    ): Response {
        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->renderNotFound();

        return $this->render('profile/profile-edit', [
            'user'     => $userContext,
            'userData' => $userData,
            'form'     => $form,
            'errors'   => $errors,
        ], $statusCode);
    }

    public function getBooks(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($currentUserId);
        try{
            $query = new BooksListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+category"),
                filters: BooksFilters::fromCreator($q, $userId),
                userFilters: UserRelationFilters::fromInput($q, $currentUserId),
                viewerId: $currentUserId
            );

            $paginatedList = $this->booksService->getList($query);
            return $this->render('profile/profile-publications', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'book'
                ], 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
        catch(ValidationException $e){
            return $this->render('profile/profile-publications', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

    public function getArticles(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($currentUserId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+type"),
                filters: ArticlesFilters::fromCreator($q, $userId),
                userFilters: UserRelationFilters::fromInput($q, $currentUserId),
                viewerId: $currentUserId
            );

            $paginatedList = $this->articlesService->getList($query);
            return $this->render('profile/profile-publications', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'article'
                ], 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
        catch(ValidationException $e){
            return $this->render('profile/profile-publications', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

}