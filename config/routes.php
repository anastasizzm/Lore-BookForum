<?php

use App\Http\Router;
use App\Http\HttpContext;
use App\Http\Response;

use App\Lib\Auth\AuthPolicy;
use App\Lib\View;
use App\Extensions\Parsers\RouteParamParser;

use App\Models\Access\Operations\Publications\CreatePublicationOperation;
use App\Models\Access\Operations\Publications\DeletePublicationOperation;
use App\Models\Access\Operations\Publications\UpdateMetaPublicationOperation;
use App\Models\Access\Operations\Publications\UpdateContentPublicationOperation;
use App\Models\Access\Operations\Publications\ReadPublicationOperation;
use App\Models\Access\Operations\Publications\ManagePeoplePublicationOperation;
use App\Models\Access\Operations\Publications\AnyPublicationOperation;
use App\Models\Access\Operations\Publications\ManageRolesPublicationOperation;
use App\Models\Access\Operations\Publications\ControlPublicationOperation;
use App\Models\Access\Operations\Posts\CreatePostOperation;
use App\Models\Access\Operations\Posts\DeletePostOperation;
use App\Models\Access\Operations\Posts\UpdatePostOperation; 
use App\Models\Access\Operations\Posts\ControlPostOperation; 
use App\Models\Access\Operations\Account\BlockAccountOperation;
use App\Models\Access\Operations\Account\ManageAccountOperation; 
use App\Models\Access\Operations\System\ManageAdditionalsSystemOperation;
use App\Models\Access\Operations\System\ManagePeopleSystemOperation;
use App\Models\Access\Operations\System\ManageRolesSystemOperation;
use App\Models\Access\Operations\System\AnySystemOperation;

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
    $router->get('/', [App\Controllers\Posts\PostsController::class, 'list'], 'home', AuthPolicy::Auth);


    // Publications
    $router->get('/books', [App\Controllers\Publications\BooksController::class, 'list'], 'books', AuthPolicy::Auth);
    $router->get('/books/drafts', [App\Controllers\Publications\BooksController::class, 'draftList'], 'books.drafts', AuthPolicy::Auth);
    $router->get('/books/saved', [App\Controllers\Publications\BooksController::class, 'savedList'], 'books.saved', AuthPolicy::Auth);
    $router->get('/books/new', [App\Controllers\Publications\BooksController::class, 'getCreate'], 'books.create', CreatePublicationOperation::class);
    $router->post('/books/new', [App\Controllers\Publications\BooksController::class, 'create'], 'books.create.submit', CreatePublicationOperation::class);
    $router->get('/books/{bookId}', [App\Controllers\Publications\BooksController::class, 'retrieve'], 'books.retrieve', ReadPublicationOperation::class);
    $router->get('/books/{bookId}/read', [App\Controllers\Publications\BooksController::class, 'read'], 'books.read', ReadPublicationOperation::class);

    $router->get('/articles', [App\Controllers\Publications\ArticlesController::class, 'list'], 'articles', AuthPolicy::Auth);
    $router->get('/articles/drafts', [App\Controllers\Publications\ArticlesController::class, 'draftList'], 'articles.drafts', AuthPolicy::Auth);
    $router->get('/articles/saved', [App\Controllers\Publications\ArticlesController::class, 'savedList'], 'articles.saved', AuthPolicy::Auth);
    $router->get('/articles/new', [App\Controllers\Publications\ArticlesController::class, 'getCreate'], 'articles.create', CreatePublicationOperation::class);
    $router->post('/articles/new', [App\Controllers\Publications\ArticlesController::class, 'create'], 'articles.create.submit', CreatePublicationOperation::class);
    $router->get('/articles/{articleId}', [App\Controllers\Publications\ArticlesController::class, 'retrieve'], 'articles.retrieve', ReadPublicationOperation::class);
    
    
    // Profile
    $router->get('/users', fn(HttpContext $ctx) => Response::html(View::render('message', ['message' => 'Page not found', 'statusCode' => 404]), 404), 'users', AuthPolicy::Auth);
    $router->get('/users/{userId}', [App\Controllers\Users\UsersController::class, 'retrieve'], 'users.profile', AuthPolicy::Auth);
    $router->get('/users/{userId}/edit', [App\Controllers\Users\UsersController::class, 'getEdit'], 'users.profile.edit', ManageAccountOperation::class);

    $router->get('/users/{userId}/books', [App\Controllers\Users\UsersController::class, 'getBooks'], 'users.profile.books', AuthPolicy::Auth);
    $router->get('/users/{userId}/articles', [App\Controllers\Users\UsersController::class, 'getArticles'], 'users.profile.articles', AuthPolicy::Auth);


    // Additional
    $router->get('/help', [App\Controllers\Additional\HelpController::class, 'getHelp'], 'help', AuthPolicy::Public);
    $router->get('/privacy', [App\Controllers\Additional\HelpController::class, 'getPrivacy'], 'privacy', AuthPolicy::Public);


    // Admin
    $router->get('/admin', [App\Controllers\Additional\AdminPanelController::class, 'dashboard'], 'admin', AuthPolicy::Admin);
    $router->get('/admin/articles', [App\Controllers\Publications\ArticlesController::class, 'adminList'], 'admin.articles', ControlPublicationOperation::class);
    $router->get('/admin/books', [App\Controllers\Publications\BooksController::class, 'adminList'], 'admin.books', ControlPublicationOperation::class);
    $router->get('/admin/categories', [App\Controllers\Additional\AdminPanelController::class, 'categories'], 'admin.categories', ManageAdditionalsSystemOperation::class);
    $router->get('/admin/genres', [App\Controllers\Additional\AdminPanelController::class, 'genres'], 'admin.genres', ManageAdditionalsSystemOperation::class);
    $router->get('/admin/types', [App\Controllers\Additional\AdminPanelController::class, 'types'], 'admin.types', ManageAdditionalsSystemOperation::class);
    $router->get('/admin/posts', [App\Controllers\Additional\AdminPanelController::class, 'getSystemRoles'], 'admin.posts', ControlPostOperation::class);
    $router->get('/admin/users', [App\Controllers\Additional\AdminPanelController::class, 'getUsers'], 'admin.users', ManagePeopleSystemOperation::class);
    $router->get('/admin/roles', [App\Controllers\Additional\AdminPanelController::class, 'getSystemRoles'], 'admin.roles', ManageRolesSystemOperation::class);
};