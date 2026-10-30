<?php
declare(strict_types=1);

namespace App\Controllers\Publications;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\BooksService;

use App\Models\Publications\PublicationShort;
use App\Forms\Publications\BookForm;

use App\Lib\I18n\Translator;
use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;

use App\Models\Queries\Publications\BooksListQuery;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Enums\BookFormat;
use App\Models\Filters\Publications\BooksFilters;
use App\Models\Filters\Publications\UserRelationFilters;
use App\Extensions\Parsers\RouteParamParser;
use App\Extensions\Parsers\QueryParser;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\NotFoundException;

use App\Constants;
use App\ErrorCodes;

final class BooksController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly BooksService $booksService,
        private readonly Translator $translator,
        private readonly UrlGenerator $url
    ){}

    public function list(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new BooksListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: BooksFilters::fromPublic($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->booksService->getListWithContext($query);
            return $this->render('library/library-list', [
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
            return $this->render('library/library-list', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

    public function draftList(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new BooksListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: BooksFilters::fromDraft($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->booksService->getListWithContext($query);
            return $this->render('drafts/drafts', [
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
            return $this->render('drafts/drafts', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

    public function savedList(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new BooksListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: BooksFilters::fromInput($q),
                userFilters: UserRelationFilters::fromSaved($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->booksService->getListWithContext($query);
            return $this->render('saved/saved-list', [
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
            return $this->render('saved/saved-list', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }    
    }

    public function retrieve(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $propsQ = PropertiesQuery::fromRaw("creator+genre+category");

        $userContext = $this->usersService->loadContext($userId);
        try{
            $item = $this->booksService->retrieveWithContext($bookId, $propsQ, $userId);
            if ($item === null)
                return $this->renderNotFound();
            
            return $this->render('book/book-details', [
                'wrapper' => $item,
                'user' => $userContext
            ]);
        }
        catch(ValidationException $e){
            return $this->render('book/book-details', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }
    }

    public function getCreate(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $userContext = $this->usersService->loadContext($userId);
        return $this->render('book/book-form', [
            'user' => $userContext
        ]);
    }

    public function create(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $formData = $context->request->body();
        try{
            $bookId = $this->booksService->create(
                BookForm::fromInput($formData),
                $userId
            );
            return Response::redirect($this->url->url('books.retrieve', ['bookId' => $bookId]));
        }
        catch(ValidationException $e){
            $userContext = $this->usersService->loadContext($userId);
            return $this->render('book/book-form', [
                'form' => $formData, 
                'errors' => $e->errors(),
                'user' => $userContext
            ], 422);
        }
    }

    public function read(HttpContext $context, string $bookId): Response
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if ($userId === null) {
            return Response::redirect($this->url->url('login'));
        }

        $format = QueryParser::optionalEnum(
            $context->request->query,
            'format',
            BookFormat::class,
        ) ?? BookFormat::Pdf;

        $publication = $this->booksService->retrieveShort($bookId);
        if ($publication === null) {
            throw new NotFoundException(
                $this->translator->t('errors.common.not_found'),
                ErrorCodes::NOT_FOUND,
            );
        }

        $fileId = $this->booksService->identifyReadContent($bookId, $format);
        if ($fileId === null) {
            throw new NotFoundException(
                $this->translator->t('errors.files.no_file'),
                ErrorCodes::NOT_FOUND,
            );
        }

        $progress = $this->booksService->getProgress(
            $userId,
            $bookId,
            $format,
        );

        return $this->render('book/book-read', [
            'book'  => $publication,
            'fileId' => $fileId->toString(),
            'previewUrl' => $this->url->url('files.preview', ['fileId' => $fileId->toString()]),
            'initial' => $progress?->position ?? $this->defaultPosition($format)
        ]);
    }

    private function defaultPosition(BookFormat $format): array
    {
        return match ($format) {
            BookFormat::Pdf  => ['pageNumber' => 1],
            BookFormat::Epub => ['chapterIndex' => 0],
            default          => ['pageNumber' => 1],
        };
    }
}