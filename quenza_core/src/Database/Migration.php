<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use Quenza\Core\Foundation\Application;
use Quenza\Core\Database\Schema\SchemaManager;

abstract class Migration
{
    public function __construct(
        protected readonly Connection $connection,
        protected readonly Application $app,
    ) {
    }

    abstract public function up(): void;

    abstract public function down(): void;

    final protected function schema(): SchemaManager
    {
        /** @var SchemaManager $schema */
        $schema = $this->app->get(SchemaManager::class);

        return $schema;
    }
}
