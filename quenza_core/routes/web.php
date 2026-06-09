<?php
declare(strict_types=1);

use Quenza\Core\Controller\Admin\CategoryController;
use Quenza\Core\Controller\Admin\CommentController;
use Quenza\Core\Controller\Admin\DashboardController;
use Quenza\Core\Controller\Admin\ExtensionController;
use Quenza\Core\Controller\Admin\MediaController;
use Quenza\Core\Controller\Admin\PageController;
use Quenza\Core\Controller\Admin\PostController;
use Quenza\Core\Controller\Admin\SettingController;
use Quenza\Core\Controller\Admin\UserController;
use Quenza\Core\Controller\AuthController;
use Quenza\Core\Controller\HomeController;
use Quenza\Core\Controller\InstallController;
use Quenza\Core\Http\Middleware\AuthMiddleware;
use Quenza\Core\Http\Middleware\CsrfMiddleware;
use Quenza\Core\Http\Middleware\GuestMiddleware;
use Quenza\Core\Http\Router;

return static function (Router $router): void {
    $router->get('/install', [InstallController::class, 'show']);
    $router->post('/install/language', [InstallController::class, 'setLanguage'], [CsrfMiddleware::class]);
    $router->post('/install/database', [InstallController::class, 'setDatabase'], [CsrfMiddleware::class]);
    $router->post('/install/site', [InstallController::class, 'install'], [CsrfMiddleware::class]);
    $router->get('/install/success', [InstallController::class, 'success']);

    $router->get('/', [HomeController::class, 'index']);
    $router->get('/articles/{slug}', [HomeController::class, 'article']);

    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class, CsrfMiddleware::class]);
    $router->get('/register', [AuthController::class, 'showRegister'], [GuestMiddleware::class]);
    $router->post('/register', [AuthController::class, 'register'], [GuestMiddleware::class, CsrfMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

    $router->get('/admin', [DashboardController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/admin/quick-draft', [DashboardController::class, 'quickDraft'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Admin Posts
    $router->get('/admin/posts', [PostController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/posts/create', [PostController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/admin/posts/store', [PostController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/admin/posts/{id}/edit', [PostController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/admin/posts/{id}/update', [PostController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/posts/{id}/delete', [PostController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Admin Categories
    $router->get('/admin/categories', [CategoryController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/categories/create', [CategoryController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/admin/categories/store', [CategoryController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/admin/categories/{id}/edit', [CategoryController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/admin/categories/{id}/update', [CategoryController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/categories/{id}/delete', [CategoryController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Admin Pages
    $router->get('/admin/pages', [PageController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/pages/create', [PageController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/admin/pages/store', [PageController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/admin/pages/{id}/edit', [PageController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/admin/pages/{id}/update', [PageController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/pages/{id}/delete', [PageController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Admin Media
    $router->get('/admin/media', [MediaController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/admin/media/upload', [MediaController::class, 'upload'], [AuthMiddleware::class]);
    $router->post('/admin/media/{id}/delete', [MediaController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Admin Users
    $router->get('/admin/users', [UserController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/users/create', [UserController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/admin/users/store', [UserController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/admin/users/{id}/update', [UserController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/users/{id}/delete', [UserController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Admin Settings
    $router->get('/admin/settings', [SettingController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/admin/settings', [SettingController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Admin Extensions
    $router->get('/admin/plugins', [ExtensionController::class, 'plugins'], [AuthMiddleware::class]);
    $router->get('/admin/themes', [ExtensionController::class, 'themes'], [AuthMiddleware::class]);

    // Admin Comments
    $router->get('/admin/comments', [CommentController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/admin/comments/{id}/approve', [CommentController::class, 'approve'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/comments/{id}/unapprove', [CommentController::class, 'unapprove'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/comments/{id}/spam', [CommentController::class, 'spam'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/comments/{id}/delete', [CommentController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

    $router->get('/{slug}', [HomeController::class, 'page']);
};
