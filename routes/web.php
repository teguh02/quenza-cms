<?php
declare(strict_types=1);

use Quenza\Core\Controller\Admin\DashboardController;
use Quenza\Core\Controller\AuthController;
use Quenza\Core\Controller\HomeController;
use Quenza\Core\Http\Middleware\AuthMiddleware;
use Quenza\Core\Http\Middleware\CsrfMiddleware;
use Quenza\Core\Http\Middleware\GuestMiddleware;
use Quenza\Core\Http\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/articles/{slug}', [HomeController::class, 'article']);

    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class, CsrfMiddleware::class]);
    $router->get('/register', [AuthController::class, 'showRegister'], [GuestMiddleware::class]);
    $router->post('/register', [AuthController::class, 'register'], [GuestMiddleware::class, CsrfMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

    $router->get('/admin', [DashboardController::class, 'index'], [AuthMiddleware::class]);
};
