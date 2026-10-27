<?php

use App\Http\Router;
use App\Http\HttpContext;
use App\Http\Response;

use App\Lib\Auth\AuthPolicy;

use App\Models\Access\Operations\Publications\CreatePublicationOperation;
use App\Models\Access\Operations\Publications\DeletePublicationOperation;
use App\Models\Access\Operations\Publications\UpdateMetaPublicationOperation;
use App\Models\Access\Operations\Publications\UpdateContentPublicationOperation;
use App\Models\Access\Operations\Publications\ReadPublicationOperation;
use App\Models\Access\Operations\Publications\ManagePeoplePublicationOperation;
use App\Models\Access\Operations\Publications\ManageRolesPublicationOperation;
use App\Models\Access\Operations\Publications\AnyPublicationOperation;
use App\Models\Access\Operations\Posts\CreatePostOperation;
use App\Models\Access\Operations\Posts\DeletePostOperation;
use App\Models\Access\Operations\Posts\UpdatePostOperation; 
use App\Models\Access\Operations\Account\BlockAccountOperation;
use App\Models\Access\Operations\Account\ManageAccountOperation; 
use App\Models\Access\Operations\System\ManageAdditionalsSystemOperation;
use App\Models\Access\Operations\System\ManagePeopleSystemOperation;
use App\Models\Access\Operations\System\ManageRolesSystemOperation;
use App\Models\Access\Operations\System\AnySystemOperation;

return function(Router $router)
{
    // Auth
    $router->post('/api/mail/resend', [App\Controllers\Api\Auth\AccountController::class, 'resendMailVerify'], 'api.sendmail', AuthPolicy::Public);
    
    $router->put('/api/users/{userId}/credits/edit', [App\Controllers\Api\Auth\AccountController::class, 'changeCredits'], 'api.users.credits.edit.submit', ManageAccountOperation::class);

    // Publications
    $router->get('/api/books', [App\Controllers\Api\Publications\BooksController::class, 'list'], 'api.books', AuthPolicy::Auth);
    $router->post('/api/books/new', [App\Controllers\Api\Publications\BooksController::class, 'create'], 'api.books.create.submit', CreatePublicationOperation::class);
    $router->post('/api/books/{bookId}/save', [App\Controllers\Api\Publications\BooksController::class, 'save'], 'api.books.save', ReadPublicationOperation::class);
    $router->delete('/api/books/{bookId}/save', [App\Controllers\Api\Publications\BooksController::class, 'deleteSave'], 'api.books.unsave', ReadPublicationOperation::class);
    $router->put('/api/books/{bookId}/rate', [App\Controllers\Api\Publications\BooksController::class, 'rate'], 'api.books.rate', ReadPublicationOperation::class);
    $router->delete('/api/books/{bookId}/rate', [App\Controllers\Api\Publications\BooksController::class, 'unrate'], 'api.books.unrate', ReadPublicationOperation::class);
    $router->post('/api/books/{bookId}/progress', [App\Controllers\Api\Publications\BooksController::class, 'upsert'], 'api.books.progress', ReadPublicationOperation::class);
    $router->post('/api/books/{bookId}/progress/finish', [App\Controllers\Api\Publications\BooksController::class, 'finish'], 'api.books.progress.finish', ReadPublicationOperation::class);
    $router->delete('/api/books/{bookId}/progress', [App\Controllers\Api\Publications\BooksController::class, 'reset'], 'api.books.progress.reset', ReadPublicationOperation::class);


    $router->get('/api/articles', [App\Controllers\Api\Publications\ArticlesController::class, 'list'], 'api.articles', AuthPolicy::Auth);
    $router->post('/api/articles/{articleId}/save', [App\Controllers\Api\Publications\ArticlesController::class, 'save'], 'api.articles.save', ReadPublicationOperation::class);
    $router->delete('/api/articles/{articleId}/save', [App\Controllers\Api\Publications\ArticlesController::class, 'deleteSave'], 'api.articles.unsave', ReadPublicationOperation::class);
    $router->put('/api/articles/{articleId}/rate', [App\Controllers\Api\Publications\ArticlesController::class, 'rate'], 'api.articles.rate', ReadPublicationOperation::class);
    $router->delete('/api/articles/{articleId}/rate', [App\Controllers\Api\Publications\ArticlesController::class, 'unrate'], 'api.articles.unrate', ReadPublicationOperation::class);

    $router->get('/api/publications/rules', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'getCommonRules'], 'api.publication.rules', AuthPolicy::Auth);

    $router->get('/api/publications/{publicationId}/roles', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'getRoles'], 'api.publication.roles', AnyPublicationOperation::class);
    $router->post('/api/publications/{publicationId}/roles', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'addRole'], 'api.publication.roles.add', ManageRolesPublicationOperation::class);
    $router->put('/api/publications/{publicationId}/roles/{roleId}', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'editRole'], 'api.publication.roles.edit', ManageRolesPublicationOperation::class);
    $router->patch('/api/publications/{publicationId}/roles/{roleId}/position', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'moveRole'], 'api.publication.roles.move', ManageRolesPublicationOperation::class);
    $router->delete('/api/publications/{publicationId}/roles/{roleId}', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'removeRole'], 'api.publication.roles.remove', ManageRolesPublicationOperation::class);
    $router->get('/api/publications/{publicationId}/users', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'getUsers'], 'api.publication.users', AnyPublicationOperation::class);
    $router->post('/api/publications/{publicationId}/users', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'addUser'], 'api.publication.users.add', ManagePeoplePublicationOperation::class);
    $router->delete('/api/publications/{publicationId}/users/{userId}', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'removeUser'], 'api.publication.users.remove', ManagePeoplePublicationOperation::class);
    $router->get('/api/publications/{publicationId}/users/{userId}/roles', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'getUserRoles'], 'api.publication.users.roles', AnyPublicationOperation::class);
    $router->post('/api/publications/{publicationId}/users/{userId}/roles', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'addRoleToUser'], 'api.publication.users.roles.add', ManagePeoplePublicationOperation::class);
    $router->delete('/api/publications/{publicationId}/users/{userId}/roles/{roleId}', [App\Controllers\Api\Publications\PublicationPermissionsController::class, 'removeUserRole'], 'api.publication.users.roles.remove', ManagePeoplePublicationOperation::class);

    // Posts
    $router->get('/api/posts', [App\Controllers\Api\Publications\PostsController::class, 'list'], 'api.post', AuthPolicy::Auth);
    $router->post('/api/posts', [App\Controllers\Api\Publications\PostsController::class, 'addComment'], 'api.post.add', CreatePostOperation::class);
    $router->get('/api/posts/rules', [App\Controllers\Api\Publications\PostsController::class, 'getCommonRules'], 'api.post.rules', AuthPolicy::Auth);
    $router->post('/api/posts/{postId}', [App\Controllers\Api\Publications\PostsController::class, 'addComment'], 'api.post.subAdd', CreatePostOperation::class);
    $router->delete('/api/posts/{postId}', [App\Controllers\Api\Publications\PostsController::class, 'removeComment'], 'api.post.remove', DeletePostOperation::class);
    
    $router->post('/api/posts/{postId}/like', [App\Controllers\Api\Publications\PostsController::class, 'setLike'], 'api.post.like', AuthPolicy::Verified);
    $router->delete('/api/posts/{postId}/like', [App\Controllers\Api\Publications\PostsController::class, 'removeLike'], 'api.post.unlike', AuthPolicy::Verified);


    // Additional
    $router->get('/api/additional/genres', [App\Controllers\Api\Additional\GenresController::class, 'list'], 'api.genres', AuthPolicy::Auth);
    $router->post('/api/additional/genres', [App\Controllers\Api\Additional\GenresController::class, 'create'], 'api.genres.create', ManageAdditionalsSystemOperation::class);
    $router->put('/api/additional/genres/{genreId}', [App\Controllers\Api\Additional\GenresController::class, 'update'], 'api.genres.update', ManageAdditionalsSystemOperation::class);
    $router->delete('/api/additional/genres/{genreId}', [App\Controllers\Api\Additional\GenresController::class, 'delete'], 'api.genres.delete', ManageAdditionalsSystemOperation::class);
    
    $router->get('/api/additional/categories', [App\Controllers\Api\Additional\CategoriesController::class, 'list'], 'api.categories', AuthPolicy::Auth);
    $router->post('/api/additional/categories', [App\Controllers\Api\Additional\CategoriesController::class, 'create'], 'api.categories.create', ManageAdditionalsSystemOperation::class);
    $router->put('/api/additional/categories/{categoryId}', [App\Controllers\Api\Additional\CategoriesController::class, 'update'], 'api.categories.update', ManageAdditionalsSystemOperation::class);
    $router->delete('/api/additional/categories/{categoryId}', [App\Controllers\Api\Additional\CategoriesController::class, 'delete'], 'api.categories.delete', ManageAdditionalsSystemOperation::class);
    
    $router->get('/api/additional/types', [App\Controllers\Api\Additional\TypesController::class, 'list'], 'api.types', AuthPolicy::Auth);
    $router->post('/api/additional/types', [App\Controllers\Api\Additional\TypesController::class, 'create'], 'api.types.create', ManageAdditionalsSystemOperation::class);
    $router->put('/api/additional/types/{typeId}', [App\Controllers\Api\Additional\TypesController::class, 'update'], 'api.types.update', ManageAdditionalsSystemOperation::class);
    $router->delete('/api/additional/types/{typeId}', [App\Controllers\Api\Additional\TypesController::class, 'delete'], 'api.types.delete', ManageAdditionalsSystemOperation::class);
    
    $router->get('/api/additional/languages', [App\Controllers\Api\Additional\LanguagesController::class, 'list'], 'api.languages', AuthPolicy::Auth);
    $router->get('/api/additional/languages/fallback', [App\Controllers\Api\Additional\LanguagesController::class, 'fallback'], 'api.languages.fallback', AuthPolicy::Auth);
    $router->post('/api/additional/languages',  [App\Controllers\Api\Additional\LanguagesController::class, 'create'], 'api.languages.create', ManageAdditionalsSystemOperation::class);
    $router->put('/api/additional/languages/{code}',  [App\Controllers\Api\Additional\LanguagesController::class, 'update'], 'api.languages.update', ManageAdditionalsSystemOperation::class);
    $router->delete('/api/additional/languages/{code}',  [App\Controllers\Api\Additional\LanguagesController::class, 'delete'], 'api.languages.delete', ManageAdditionalsSystemOperation::class);

    $router->get('/api/additional/roles', [App\Controllers\Api\Additional\RolesController::class, 'list'], 'api.roles', AuthPolicy::Auth);
    $router->post('/api/additional/roles', [App\Controllers\Api\Additional\RolesController::class, 'create'], 'api.roles.create', ManageAdditionalsSystemOperation::class);
    $router->put('/api/additional/roles/{roleId}', [App\Controllers\Api\Additional\RolesController::class, 'update'], 'api.roles.update', ManageAdditionalsSystemOperation::class);
    $router->delete('/api/additional/roles/{roleId}', [App\Controllers\Api\Additional\RolesController::class, 'delete'], 'api.roles.delete', ManageAdditionalsSystemOperation::class);

    // Users
    $router->put('/api/users/{userId}/profile/edit', [App\Controllers\Api\Users\UsersController::class, 'editProfile'], 'api.users.profile.edit.submit', ManageAccountOperation::class);
    

    // Files
    $router->post('/api/files/upload', [App\Controllers\Api\Files\FileController::class, 'upload'], 'files.upload', AuthPolicy::Verified);
    $router->get('/api/files/{fileId}/download', [App\Controllers\Api\Files\FileController::class, 'download'], 'files.download', AuthPolicy::Public);
    $router->get('/api/files/{fileId}/preview', [App\Controllers\Api\Files\FileController::class, 'preview'], 'files.preview', AuthPolicy::Public);

    // System
    $router->get('/api/system/rules', [App\Controllers\Api\System\SystemPermissionsController::class, 'getCommonRules'], 'api.system.rules', AuthPolicy::Auth);
    
    $router->get('/api/system/roles', [App\Controllers\Api\System\SystemPermissionsController::class, 'getRoles'], 'api.system.roles', AnySystemOperation::class);
    $router->post('/api/system/roles', [App\Controllers\Api\System\SystemPermissionsController::class, 'addRole'], 'api.system.roles.add', ManageRolesSystemOperation::class);
    $router->put('/api/system/roles/{roleId}', [App\Controllers\Api\System\SystemPermissionsController::class, 'editRole'], 'api.system.roles.edit', ManageRolesSystemOperation::class);
    $router->patch('/api/system/roles/{roleId}/position', [App\Controllers\Api\System\SystemPermissionsController::class, 'moveRole'], 'api.system.roles.move', ManageRolesSystemOperation::class);
    $router->delete('/api/system/roles/{roleId}', [App\Controllers\Api\System\SystemPermissionsController::class, 'removeRole'], 'api.system.roles.remove', ManageRolesSystemOperation::class);
    $router->get('/api/system/users', [App\Controllers\Api\System\SystemPermissionsController::class, 'getUsers'], 'api.system.users', AnySystemOperation::class);
    $router->post('/api/system/users', [App\Controllers\Api\System\SystemPermissionsController::class, 'addUser'], 'api.system.users.add', ManagePeopleSystemOperation::class);
    $router->get('/api/system/users/{userId}/roles', [App\Controllers\Api\System\SystemPermissionsController::class, 'getUserRoles'], 'api.system.users.roles', AnySystemOperation::class);
    $router->post('/api/system/users/{userId}/roles', [App\Controllers\Api\System\SystemPermissionsController::class, 'addRoleToUser'], 'api.system.users.roles.add', ManagePeopleSystemOperation::class);
    $router->delete('/api/system/users/{userId}/roles/{roleId}', [App\Controllers\Api\System\SystemPermissionsController::class, 'removeUserRole'], 'api.system.users.roles.remove', ManagePeopleSystemOperation::class);
};