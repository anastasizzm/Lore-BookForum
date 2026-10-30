<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Permissions\PublicationPermissionsService;
use App\Services\Publications\ArticlesService;

use App\Models\Queries\Publications\ArticlesListQuery;
use App\Extensions\Parsers\RouteParamParser;
use App\Forms\Publications\ArticleForm;
use App\Forms\Publications\ArticlePatchForm;
use App\Forms\Publications\ArticleContentForm;
use App\Models\Enums\ServiceType;
use App\Models\Enums\ArticleContentMode;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Attributes\Inject;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;
use App\Http\UrlGenerator;

use App\ErrorCodes;
use App\Constants;

final class ArticlesController extends PublicationsController
{
    public function __construct(
        #[Inject(key: ServiceType::Publication)]
        CommonRuleProvider $ruleProvider,
        ArticlesService $articlesService,
        PublicationPermissionsService $permsService,
        private readonly UrlGenerator $url
    ){
        parent::__construct($ruleProvider, $articlesService, $permsService);
    }

    public function list(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $query = ArticlesListQuery::fromInput($context->request->query, $userId);
        $paginatedList = $this->service->getListWithContext($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }

    public function getContent(HttpContext $context, string $id)
    {
        $articleId = RouteParamParser::positiveInt(['id' => $id], $id);

        $data = $this->service->getContent($articleId);
        if ($data->getType() === ArticleContentMode::Book)
        {
            $book = $data->getBook();
            $bookRedirect = $this->url->url("books.read", ['bookId' => $book->bookId]) . '?page=' . $book->pageStart;

            $array = $data->toArray();
            $array['book']['redirectUrl'] = $bookRedirect;
            return $this->jsonObject($array);
        }

        return $this->jsonObject($data);
    }

    public function create(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $formData = $context->request->body();
        $articleId = $this->service->create(
            ArticleForm::fromInput($formData),
            $userId
        );
        return $this->jsonCreatedId($articleId);
    }

    public function update(HttpContext $context, string $id) 
    {
        $articleId = RouteParamParser::positiveInt(['id' => $id], 'id');

        $formData = $context->request->body();
        $this->service->update($articleId, ArticlePatchForm::fromInput($formData));
        return $this->jsonEmpty();
    }

    public function updateContent(HttpContext $context, string $id) 
    {
        $articleId = RouteParamParser::positiveInt(['id' => $id], 'id');

        $formData = $context->request->body();
        $expectedVersion = $this->requireVersion($context);
        $newVersion = $this->service->updateContent($articleId, ArticleContentForm::fromInput($formData), $expectedVersion);
        return $this->jsonEmpty()->withHeader('ETag', sprintf('"%d"', $newVersion));
    }

    private function requireVersion(HttpContext $context): int
    {
        $ifMatch = $context->header('If-Match');
        if ($ifMatch === null || $ifMatch === '') {
            throw new HttpException('If-Match header is required', 428, ErrorCodes::INVALID_HEADER);
        }
        $stripped = trim($ifMatch, '"');
        if (!ctype_digit($stripped)) {
            throw new HttpException('Invalid If-Match header', 400, ErrorCodes::INVALID_HEADER);
        }
        return (int)$stripped;
    }
}