<?php
declare(strict_types=1);

namespace Tests\Integration\Database;

use Quenza\Core\Database\Schema\Blueprint;
use Quenza\Core\Database\Schema\SchemaManager;
use Tests\Support\DatabaseTestCase;

final class SchemaManagerTest extends DatabaseTestCase
{
    public function test_schema_manager_can_create_and_drop_custom_table(): void
    {
        /** @var SchemaManager $schema */
        $schema = $this->app->get(SchemaManager::class);

        $schema->create('test_logs', static function (Blueprint $table): void {
            $table->id();
            $table->string('message', 150);
            $table->timestamps();
            $table->index('message', 'idx_qz_test_logs_message');
        });

        self::assertTrue($schema->hasTable('test_logs'));

        $this->database()->insert('test_logs', [
            'message' => 'Schema test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        self::assertSame(1, $this->database()->table('test_logs')->count());

        $schema->dropIfExists('test_logs');

        self::assertFalse($schema->hasTable('test_logs'));
    }
}
