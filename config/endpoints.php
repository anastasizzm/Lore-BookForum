<?php

use App\Http\Router;
use App\Http\HttpContext;
use App\Http\Response;

use App\Lib\Auth\AuthPolicy;

return function(Router $router)
{
    // Publications
    $router->get('/api/books', [App\Controllers\Api\Publications\BooksController::class, 'list'], 'api.books', AuthPolicy::Auth);
    $router->get('/api/articles', [App\Controllers\Api\Publications\ArticlesController::class, 'list'], 'api.articles', AuthPolicy::Auth);


    // Posts
    $router->post('/api/posts/{postId}/like', [App\Controllers\Api\Publications\PostsController::class, 'setLike'], 'api.post.like', AuthPolicy::Verified);
    $router->delete('/api/posts/{postId}/like', [App\Controllers\Api\Publications\PostsController::class, 'removeLike'], 'api.post.unlike', AuthPolicy::Verified);


    // Additional
    $router->get('/api/additional/genres', [App\Controllers\Api\Additional\GenresController::class, 'list'], 'api.genres', AuthPolicy::Auth);

    $router->get('/api/additional/categories', [App\Controllers\Api\Additional\CategoriesController::class, 'list'], 'api.categories', AuthPolicy::Auth);
};