<?php

use App\Constants;
use App\Http\Router;
use App\Http\HttpContext;
use App\Http\Response;

use App\Lib\Auth\AuthPolicy;
use App\Lib\Data\Database;
use App\Lib\Settings;
use App\Lib\View;

use App\Repositories\Users\UsersRepository;

return function(Router $router)
{
    // Auth
    $router->post('/api/mail/resend', [App\Controllers\Api\Auth\AccountController::class, 'resendMailVerify'], 'api.sendmail', AuthPolicy::Public);
    
    $router->put('/api/users/{userId}/credits/edit', [App\Controllers\Api\Auth\AccountController::class, 'changeCredits'], 'api.users.credits.edit.submit', 'profile_owner');

    // Publications
    $router->get('/api/books', [App\Controllers\Api\Publications\BooksController::class, 'list'], 'api.books', AuthPolicy::Auth);
    $router->post('/api/books/{bookId}/save', [App\Controllers\Api\Publications\BooksController::class, 'save'], 'api.books.save', AuthPolicy::Verified);
    $router->delete('/api/books/{bookId}/save', [App\Controllers\Api\Publications\BooksController::class, 'deleteSave'], 'api.books.unsave', AuthPolicy::Verified);

    $router->get('/api/articles', [App\Controllers\Api\Publications\ArticlesController::class, 'list'], 'api.articles', AuthPolicy::Auth);
    $router->post('/api/articles/{articleId}/save', [App\Controllers\Api\Publications\ArticlesController::class, 'save'], 'api.articles.save', AuthPolicy::Verified);
    $router->delete('/api/articles/{articleId}/save', [App\Controllers\Api\Publications\ArticlesController::class, 'deleteSave'], 'api.articles.unsave', AuthPolicy::Verified);


    // Posts
    $router->get('/api/posts', [App\Controllers\Api\Publications\PostsController::class, 'list'], 'api.post', AuthPolicy::Auth);
    $router->post('/api/posts', [App\Controllers\Api\Publications\PostsController::class, 'addComment'], 'api.post.add', AuthPolicy::Verified);
    $router->post('/api/posts/{postId}', [App\Controllers\Api\Publications\PostsController::class, 'addComment'], 'api.post.subAdd', AuthPolicy::Verified);
    $router->delete('/api/posts/{postId}', [App\Controllers\Api\Publications\PostsController::class, 'removeComment'], 'api.post.remove', 'post_owner');
    
    $router->post('/api/posts/{postId}/like', [App\Controllers\Api\Publications\PostsController::class, 'setLike'], 'api.post.like', AuthPolicy::Verified);
    $router->delete('/api/posts/{postId}/like', [App\Controllers\Api\Publications\PostsController::class, 'removeLike'], 'api.post.unlike', AuthPolicy::Verified);


    // Additional
    $router->get('/api/additional/genres', [App\Controllers\Api\Additional\GenresController::class, 'list'], 'api.genres', AuthPolicy::Auth);
    $router->get('/api/additional/categories', [App\Controllers\Api\Additional\CategoriesController::class, 'list'], 'api.categories', AuthPolicy::Auth);
    $router->get('/api/additional/types', [App\Controllers\Api\Additional\TypesController::class, 'list'], 'api.types', AuthPolicy::Auth);
    $router->get('/api/additional/languages', [App\Controllers\Api\Additional\LanguagesController::class, 'list'], 'api.languages', AuthPolicy::Auth);
    $router->get('/api/additional/languages/fallback', [App\Controllers\Api\Additional\LanguagesController::class, 'fallback'], 'api.languages.fallback', AuthPolicy::Auth);


    // Users
    $router->put('/api/users/{userId}/profile/edit', [App\Controllers\Api\Users\UsersController::class, 'editProfile'], 'api.users.profile.edit.submit', 'profile_owner');


    // Смена пароля залогиненным пользователем — страница auth/change-password.php
    // (без письма и токена). Замыкания живут ЗДЕСЬ, а не в routes.php: routes.php
    // заморожен (правки запрещены), Router регистрирует роуты из обоих файлов.
    // Логику проверки/сохранения берут из существующих репозиториев (app/ не трогаем).
    $usersRepo = fn (): UsersRepository => new UsersRepository(
        new Database(new Settings(require __DIR__ . '/settings.php'))
    );

    $router->get('/auth/change-password', function (HttpContext $context) use ($usersRepo) {
        // $user нужен main.php (аватар в сайдбаре) — как в контроллерах
        $userId = (int) ($context->attribute(Constants::USER_ID_ATTR) ?: 0);
        $me = $userId > 0 ? $usersRepo()->loadContext($userId) : null;

        return Response::html(View::render('auth/change-password', [
            'changed' => $context->query('changed') === '1',
            'user'    => $me,
        ]));
    }, 'password.change', AuthPolicy::Auth);

    $router->post('/auth/change-password', function (HttpContext $context) use ($usersRepo) {
        $userId = (int) ($context->attribute(Constants::USER_ID_ATTR) ?: 0);
        if ($userId <= 0) return Response::redirect('/auth/login');

        $current  = trim((string) $context->input('current_password', ''));
        $password = (string) $context->input('password', '');
        $confirm  = (string) $context->input('password_confirm', '');

        $errors = [];
        if ($current === '') {
            $errors['current_password'] = ['Enter your current password'];
        }
        if (strlen($password) < 8) {
            $errors['password'] = ['Password must be at least 8 characters'];
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = ['Passwords are different'];
        }

        $repo = $usersRepo();

        if (!$errors) {
            $account = $repo->retrieve($userId);
            $credits  = $account !== null ? $repo->getAuthCredits($account->username) : null;
            if ($credits === null || !password_verify($current, $credits->password)) {
                $errors['current_password'] = ['Current password is incorrect'];
            }
        }

        if (!$errors) {
            $repo->changePassword($userId, password_hash($password, PASSWORD_DEFAULT));
            // lang никуда не девается (куки LocaleMiddleware не пишет) —
            // переносим его через редирект, как в остальных ссылках
            $to = '/auth/change-password?changed=1';
            $lang = $context->query('lang');
            if (is_string($lang) && $lang !== '') $to .= '&lang=' . rawurlencode($lang);
            return Response::redirect($to);
        }

        return Response::html(View::render('auth/change-password', ['errors' => $errors]));
    }, 'password.change.submit', AuthPolicy::Auth);
};