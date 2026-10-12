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
    $router->get('/auth/login', [App\Controllers\Auth\AuthController::class, 'getLogin'], 'login', AuthPolicy::Public);
    $router->post('/auth/login', [App\Controllers\Auth\AuthController::class, 'login'], 'login.submit', AuthPolicy::Public);

    $router->get('/auth/register', [App\Controllers\Auth\AuthController::class, 'getRegister'], 'register', AuthPolicy::Public);
    $router->post('/auth/register', [App\Controllers\Auth\AuthController::class, 'register'], 'register.submit', AuthPolicy::Public);

    $router->post('/auth/logout', [App\Controllers\Auth\AuthController::class, 'logout'], 'logout', AuthPolicy::Auth);
    
    $router->get('/auth/verify/{token}', [App\Controllers\Auth\AccountController::class, 'verifyMail'], 'verify.mail', AuthPolicy::Public);

    $router->get('/auth/password-reset', [App\Controllers\Auth\AccountController::class, 'getPasswordMail'], 'password.email', AuthPolicy::Public);
    $router->post('/auth/password-reset', [App\Controllers\Auth\AccountController::class, 'passwordMail'], 'password.email.submit', AuthPolicy::Public);
    $router->get('/auth/password-reset/{token}', [App\Controllers\Auth\AccountController::class, 'getPasswordReset'], 'password.reset', AuthPolicy::Public);
    $router->post('/auth/password-reset/submit', [App\Controllers\Auth\AccountController::class, 'passwordReset'], 'password.reset.submit', AuthPolicy::Public);

    // Feed
    $router->get('/', [App\Controllers\Feed\FeedController::class, 'list'], 'home', AuthPolicy::Auth);


    // Publications
    $router->get('/books', [App\Controllers\Publications\BooksController::class, 'list'], 'books', AuthPolicy::Auth);
    $router->get('/books/saved', [App\Controllers\Publications\BooksController::class, 'savedList'], 'books.saved', AuthPolicy::Auth);
    $router->get('/books/{bookId}', [App\Controllers\Publications\BooksController::class, 'retrieve'], 'books.retrieve', AuthPolicy::Auth);
    // Чтение книги (страница без JS: toolbar + canvas)
    $router->get('/books/{bookId}/read', function (HttpContext $ctx, $bookId) {
        return Response::html(View::render('book/book-read', [
            'bookId' => (int) $bookId,
            'page'   => max(1, (int) $ctx->query('page')),
        ]));
    }, 'books.read', AuthPolicy::Auth);
    
    $router->get('/articles', [App\Controllers\Publications\ArticlesController::class, 'list'], 'articles', AuthPolicy::Auth);
    $router->get('/articles/saved', [App\Controllers\Publications\ArticlesController::class, 'savedList'], 'articles.saved', AuthPolicy::Auth);
    $router->get('/articles/{articleId}', [App\Controllers\Publications\ArticlesController::class, 'retrieve'], 'articles.retrieve', AuthPolicy::Auth);
    

    // Profile
    $router->get('/users', fn(HttpContext $ctx) => Response::html(View::render('message', ['message' => 'Page not found', 'statusCode' => 404]), 404), 'users', AuthPolicy::Auth);
    $router->get('/users/{userId}', [App\Controllers\Users\UsersController::class, 'retrieve'], 'users.profile', AuthPolicy::Auth);
    $router->get('/users/{userId}/profile/edit', [App\Controllers\Users\UsersController::class, 'getEdit'], 'users.profile.edit', 'profile_owner');

    $router->get('/users/{userId}/books', [App\Controllers\Users\UsersController::class, 'getBooks'], 'users.profile.books', AuthPolicy::Auth);
    $router->get('/users/{userId}/articles', [App\Controllers\Users\UsersController::class, 'getArticles'], 'users.profile.articles', AuthPolicy::Auth);
};