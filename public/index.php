<?php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

header('Content-Type: text/plain; charset=UTF-8');

echo sprintf(
    "%s bootstrap siap. Lanjutkan ke dashboard dan routing pada tahap berikutnya.\n",
    (string) config('app.name', 'Quenza CMS'),
);
