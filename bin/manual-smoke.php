<?php
declare(strict_types=1);

use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Database\Migrator;
use Quenza\Core\Database\SeederRunner;
use Quenza\Core\Database\Schema\SchemaManager;

$app = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

/** @var Migrator $migrator */
$migrator = $app->get(Migrator::class);

/** @var SeederRunner $seederRunner */
$seederRunner = $app->get(SeederRunner::class);

/** @var DatabaseManager $database */
$database = $app->get(DatabaseManager::class);

/** @var SchemaManager $schema */
$schema = $app->get(SchemaManager::class);

$migrator->fresh();
$seederRunner->run();

$requiredTables = [
    'migrations',
    'roles',
    'users',
    'user_roles',
    'auth_attempts',
    'posts',
    'categories',
    'post_categories',
    'activity_logs',
    'media',
    'options',
    'menus',
    'menu_items',
];

foreach ($requiredTables as $table) {
    if (!$schema->hasTable($table)) {
        fwrite(STDERR, sprintf("[FAIL] Tabel %s tidak ditemukan.\n", $database->tableName($table)));

        exit(1);
    }
}

$roleCount = $database->table('roles')->count();
$optionCount = $database->table('options')->count();

if ($roleCount < 4) {
    fwrite(STDERR, sprintf("[FAIL] Seeder role belum lengkap. Ditemukan %d role.\n", $roleCount));

    exit(1);
}

if ($optionCount < 5) {
    fwrite(STDERR, sprintf("[FAIL] Seeder option belum lengkap. Ditemukan %d option.\n", $optionCount));

    exit(1);
}

fwrite(STDOUT, "[OK] Quenza CMS smoke test selesai.\n");
fwrite(STDOUT, sprintf("Driver: %s\n", $database->driver()->value));
fwrite(STDOUT, sprintf("Tabel inti: %d\n", count($requiredTables)));
fwrite(STDOUT, sprintf("Role default: %d\n", $roleCount));
fwrite(STDOUT, sprintf("Option default: %d\n", $optionCount));
