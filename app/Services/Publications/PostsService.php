<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PostsRepository;
use App\Forms\Queries\PaginationQuery;

final class PostsService
{
    public function __construct(
        private readonly PostsRepository $postsRepo
    ){}

    public function topList(PaginationQuery $pageQ, string $search) : array
    {
        $errors = $pageQ->validate();
        if (!empty($errors)) throw new ValidationException($errors);

        $items = $postsRepo->getTopList($pageQ->page, $pageQ->pageSize, $search);
        return [
            'items' => $items['items'], 
            'meta' => [
                'hasNext' => $items['hasNext'],
                'page' => $items['page'],
                'pageSize' => $items['pageSize']
            ]
        ];
    }
}