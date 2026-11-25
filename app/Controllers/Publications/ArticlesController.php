<?php
declare(strict_types=1);

namespace App\Controllers\Publications;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\ArticlesService;
use App\Services\Publications\BooksService;

use App\Models\Publications\PublicationShort;
use App\Models\Enums\ArticleContentMode;
use App\Models\Enums\BookFormat;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;

use App\Models\Queries\Publications\ArticlesListQuery;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Filters\Publications\ArticlesFilters;
use App\Models\Filters\Publications\UserRelationFilters;
use App\Forms\Publications\ArticleForm;
use App\Extensions\Parsers\RouteParamParser;
use App\Extensions\Parsers\QueryParser;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\NotFoundException;
use App\Lib\I18n\Translator;
use App\ErrorCodes;

use App\Constants;

final class ArticlesController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly ArticlesService $articlesService,
        private readonly BooksService $booksService,
        private readonly Translator $translator,
        private readonly UrlGenerator $url
    ){}

    public function list(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+type"),
                filters: ArticlesFilters::fromPublic($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('library/library-list', [
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
            return $this->render('library/library-list', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }    
    }

    public function adminList(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+owner+genre"),
                filters: ArticlesFilters::fromPublic($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );
    
            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('admin/articles', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'article'
                ], 
                'user' => $userContext,
            ]);
        }
        catch(ValidationException $e){
            return $this->render('admin/articles', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }    
    }

    public function draftList(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+type"),
                filters: ArticlesFilters::fromDraft($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('drafts/drafts', [
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
            return $this->render('drafts/drafts', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }    
    }

    public function savedList(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+type"),
                filters: ArticlesFilters::fromInput($q),
                userFilters: UserRelationFilters::fromSaved($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('saved/saved-list', [
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
            return $this->render('saved/saved-list', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }   
    }

    public function retrieve(HttpContext $context, string $articleId)
    {
        $articleId = RouteParamParser::positiveInt(['a' => $articleId], 'a');
        
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $propsQ = PropertiesQuery::fromRaw("creator+genre+type+book");

        $userContext = $this->usersService->loadContext($userId);
        try{
            $item = $this->articlesService->retrieveWithContext($articleId, $propsQ, $userId);
            if ($item === null)
                return $this->renderNotFound();

            // ContentData (book source + page range + text) is a separate model
            // from Article and is not loaded by the retrieve query. Load it once
            // here; legacy rows may have no content record at all, so a missing
            // row must not turn the details page into a 500.
            try {
                $contentData = $this->articlesService->getContent($articleId);
            } catch (NotFoundException) {
                $contentData = null;
            }

            return $this->render('article/article-details', [
                'wrapper' => $item,
                'contentData' => $contentData,
                'user' => $userContext
            ]);
        }
        catch(ValidationException $e){
            return $this->render('article/article-details', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }
    }

    public function read(HttpContext $context, string $articleId): Response
    {
        $articleId = RouteParamParser::positiveInt(['a' => $articleId], 'a');

        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $content = $this->articlesService->getContent($articleId);
        $bookData = $content->getBookData();

        // This reader is only for excerpts: a standalone article has its own
        // modal/reader and must not be opened as a book file.
        if ($content->getType() !== ArticleContentMode::Book || $bookData === null) {
            throw new NotFoundException(
                $this->translator->t('errors.common.not_found'),
                ErrorCodes::NOT_FOUND,
            );
        }

        $book = $this->booksService->retrieveShort($bookData->bookId);
        if ($book === null) {
            throw new NotFoundException(
                $this->translator->t('errors.common.not_found'),
                ErrorCodes::NOT_FOUND,
            );
        }

        $fileId = $this->booksService->identifyReadContent($bookData->bookId, BookFormat::Pdf);
        if ($fileId === null) {
            throw new NotFoundException(
                $this->translator->t('errors.files.no_file'),
                ErrorCodes::NOT_FOUND,
            );
        }

        $article = $this->articlesService->retrieveShort($articleId);
        $pageStart = max(1, (int) $bookData->pageStart);
        $pageEnd = max($pageStart, (int) $bookData->pageEnd);

        $userContext = $this->usersService->loadContext($userId);
        return $this->render('book/book-read', [
            'book'          => $book,
            'bookId'        => $bookData->bookId,
            'fileId'        => $fileId->toString(),
            'previewUrl'    => $this->url->url('files.preview', ['fileId' => $fileId->toString()]),
            'initial'       => ['pageNumber' => $pageStart],
            'pageStart'     => $pageStart,
            'pageEnd'       => $pageEnd,
            'trackProgress' => false,
            'readerTitle'   => $article?->title ?? $book->title,
            'backUrl'       => $this->url->url('articles.retrieve', ['articleId' => $articleId]),
            'backLabelKey'  => 'common.reader.back_article',
            'user'          => $userContext,
        ]);
    }

    public function getCreate(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $userContext = $this->usersService->loadContext($userId);
        return $this->render('article/article-form', [
            'user' => $userContext
        ]);
    }

    public function create(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url($this->url->url('login', query: ['redirect' => $context->request->path])));

        $formData = $context->request->body();
        try{
            $articleId = $this->articlesService->create(
                ArticleForm::fromInput($formData),
                $userId
            );
            return Response::redirect($this->url->url('articles.retrieve', ['articleId' => $articleId]));
        }
        catch(ValidationException $e){
            $userContext = $this->usersService->loadContext($userId);
            return $this->render('article/article-form', [
                'form' => $formData, 
                'errors' => $e->errors(),
                'user' => $userContext
            ], 422);
        }
    }
}