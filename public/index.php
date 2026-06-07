<?php
declare(strict_types=1);

use Quenza\Core\Http\HttpKernel;

$app = require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

/** @var HttpKernel $kernel */
$kernel = $app->get(HttpKernel::class);
$response = $kernel->handleGlobals();
$response->send();
