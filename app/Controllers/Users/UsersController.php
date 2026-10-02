<?php
declare(strict_types=1);

namespace App\Controllers\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\BooksService;
use App\Services\Publications\ArticlesService;

use App\Http\HttpContext;
use App\Http\Response;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\StatusQuery;
use App\Models\Queries\TypeQuery;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly BooksService $booksService,
        private readonly ArticlesService $articlesService
    ){}

    public function retrieve(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->render('message', ['message' => 'The profile is not found', 'statusCode' => 404]);

        return $this->render('profile/profile', ['user' => $userContext, 'userData' => $userData]);
    }

    public function getEdit(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->render('message', ['message' => 'The profile is not found', 'statusCode' => 404]);

        return $this->render('profile/profile-edit', ['user' => $userContext, 'userData' => $userData]);
    }

    public function getBooks(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $query = $context->request->query;
        $pageQ = PaginationQuery::fromInput($query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = self::cleanSearch($context->query('q', ''));
        $filterState = $context->query('f', 'closed');
        $genreId = filter_var($context->query('genre'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $isbn = self::cleanIsbn($context->query('isbn'));

        $userContext = $this->usersService->loadContext($currentUserId);
        try{
            $paginatedList = $this->booksService->getList(
                $pageQ, $searchQ, $propsQ, $currentUserId,
                sort: SortQuery::fromInput($query),
                status: StatusQuery::fromInput($query),
                genreId: $genreId,
                creatorId: $userId,
                isbn: $isbn
            );
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
                'innerMessages' => array_map(
                    static fn(string $item, array $fails) => new InnerMessage(InnerMessageType::Error, $item, implode("\n", $fails)), 
                    array_keys($e->errors), 
                    $e->errors), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

    public function getArticles(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $query = $context->request->query;
        $pageQ = PaginationQuery::fromInput($query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = self::cleanSearch($context->query('q', ''));
        $filterState = $context->query('f', 'closed');
        $genreId = filter_var($context->query('genre'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $doi = self::cleanDoi($context->query('doi'));

        $userContext = $this->usersService->loadContext($currentUserId);
        try{
            $paginatedList = $this->articlesService->getList(
                $pageQ, $searchQ, $propsQ, $currentUserId,
                sort: SortQuery::fromInput($query),
                status: StatusQuery::fromInput($query),
                genreId: $genreId,
                creatorId: $userId,
                doi: $doi,
                type: TypeQuery::fromInput($query)
            );
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
                'innerMessages' => array_map(
                    static fn(string $item, array $fails) => new InnerMessage(InnerMessageType::Error, $item, implode("\n", $fails)), 
                    array_keys($e->errors), 
                    $e->errors), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

    /** Search text: only a trimmed string, max 200 chars. */
    private static function cleanSearch(mixed $raw): string
    {
        if (!is_string($raw)) return '';
        return mb_substr(trim($raw), 0, 200);
    }

    /**
     * ISBN prefix: hyphens/spaces removed, upper-cased.
     * Valid: 1-13 digits, or 9 digits + X (ISBN-10). Anything else => null (filter ignored).
     */
    private static function cleanIsbn(mixed $raw): ?string
    {
        if (!is_string($raw)) return null;
        $s = strtoupper(preg_replace('/[\s-]+/', '', $raw) ?? '');
        if ($s === '') return null;
        return preg_match('/^(\d{1,13}|\d{9}X)$/', $s) === 1 ? $s : null;
    }

    /**
     * DOI prefix: "1", "10", "10.<0-9 digits>" or "10.<4-9 digits>/<suffix>".
     * Anything else => null (filter ignored).
     */
    private static function cleanDoi(mixed $raw): ?string
    {
        if (!is_string($raw)) return null;
        $s = trim($raw);
        if ($s === '' || strlen($s) > 200) return null;
        return preg_match('/^(1|10|10\.\d{0,9}|10\.\d{4,9}\/\S*)$/', $s) === 1 ? $s : null;
    }
}