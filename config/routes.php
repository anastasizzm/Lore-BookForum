<?php

use App\Http\Router;
use App\Http\HttpContext;
use App\Http\Response;

use App\Lib\Auth\AuthPolicy;
use App\Lib\View;

return function(Router $router)
{
    $router->get('/pages/test', function (HttpContext $context){
        $page = $context->query('page');
        if (!is_string($page)) return Response::html("<p>Provide page name in ?page query</p>");

        return Response::html(View::render($page, $context->request->query));
    }, 'test_pages');

    $router->get('/tests/verified', function (HttpContext $context) {
        return Response::html("<h1>You are verified</h1>");
    }, 'test.verified', AuthPolicy::Verified);

    $router->get('/tests/auth', function (HttpContext $context) {
        return Response::html("<h1>You are authenticated</h1>");
    }, 'test.auth', AuthPolicy::Auth);

    $router->get('/tests', function (HttpContext $context) {
        return Response::html("<h1>Test completed successfully</h1>");
    }, 'test', AuthPolicy::Auth);

    // Auth
    $router->get('/login', [App\Controllers\Auth\AuthController::class, 'getLogin'], 'login', AuthPolicy::Public);
    $router->post('/login', [App\Controllers\Auth\AuthController::class, 'login'], 'login.submit', AuthPolicy::Public);

    $router->get('/register', [App\Controllers\Auth\AuthController::class, 'getRegister'], 'register', AuthPolicy::Public);
    $router->post('/register', [App\Controllers\Auth\AuthController::class, 'register'], 'register.submit', AuthPolicy::Public);

    $router->post('/logout', [App\Controllers\Auth\AuthController::class, 'logout'], 'logout', AuthPolicy::Auth);
    $router->get('/verify/{token}', [App\Controllers\Auth\AuthController::class, 'mailVerify'], 'verify.mail', AuthPolicy::Public);


    // Feed
    $router->get('/', [App\Controllers\Feed\FeedController::class, 'list'], 'home', AuthPolicy::Auth);


    // Publications
    $router->get('/books', [App\Controllers\Publications\BooksController::class, 'list'], 'books', AuthPolicy::Auth);
    $router->get('/books/saved', [App\Controllers\Publications\BooksController::class, 'savedList'], 'books.saved', AuthPolicy::Auth);
    $router->get('/articles', [App\Controllers\Publications\ArticlesController::class, 'list'], 'articles', AuthPolicy::Auth);
    $router->get('/articles/saved', [App\Controllers\Publications\ArticlesController::class, 'savedList'], 'articles.saved', AuthPolicy::Auth);
    
    // Profile
    $router->get('/users', fn(HttpContext $ctx) => Response::html(View::render('message', ['message' => 'Page not found', 'statusCode' => 404]), 404), 'users', AuthPolicy::Auth);
    $router->get('/users/{userId}', [App\Controllers\Users\UsersController::class, 'retrieve'], 'users.profile', AuthPolicy::Auth);
    $router->get('/users/{userId}/edit', [App\Controllers\Users\UsersController::class, 'getEdit'], 'users.profile.edit', 'profile_owner');

    $router->get('/users/{userId}/books', [App\Controllers\Users\UsersController::class, 'getBooks'], 'users.profile.books', AuthPolicy::Auth);
    $router->get('/users/{userId}/articles', [App\Controllers\Users\UsersController::class, 'getArticles'], 'users.profile.articles', AuthPolicy::Auth);
};