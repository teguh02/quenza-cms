<?php
declare(strict_types=1);

use Quenza\Core\Http\HttpKernel;

$app = require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

/** @var HttpKernel $kernel */
$kernel = $app->get(HttpKernel::class);

if (($_SERVER['REQUEST_URI'] ?? '') === '/install.php') {
    $_SERVER['REQUEST_URI'] = '/install';
    $_SERVER['PATH_INFO'] = '/install';
}

$response = $kernel->handleGlobals();
$response->send();
