<?php
declare(strict_types=1);

namespace Tests\Integration\Database;

use Tests\Support\DatabaseTestCase;

final class DatabaseManagerTest extends DatabaseTestCase
{
    public function test_update_or_insert_creates_and_updates_option_records(): void
    {
        $created = $this->database()->updateOrInsert('options', ['option_name' => 'testing_option'], [
            'option_value' => 'value-1',
            'autoload' => 1,
        ]);

        self::assertTrue($created);

        $updated = $this->database()->updateOrInsert('options', ['option_name' => 'testing_option'], [
            'option_value' => 'value-2',
            'autoload' => 0,
        ]);

        self::assertFalse($updated);

        $option = $this->database()->table('options')->where('option_name', 'testing_option')->first();

        self::assertNotNull($option);
        self::assertSame('value-2', $option['option_value']);
        self::assertSame(0, (int) $option['autoload']);
    }

    public function test_insert_or_ignore_does_not_duplicate_role_assignment(): void
    {
        $userId = $this->database()->insertGetId('users', [
            'full_name' => 'Pivot Tester',
            'email' => 'pivot@example.com',
            'password_hash' => '$2y$10$placeholderplaceholderplaceholderplaceholderplaceholderpl',
            'locale' => 'id',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $inserted = $this->database()->insertOrIgnore('user_roles', [
            'user_id' => $userId,
            'role_id' => 1,
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        $ignored = $this->database()->insertOrIgnore('user_roles', [
            'user_id' => $userId,
            'role_id' => 1,
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        self::assertTrue($inserted);
        self::assertFalse($ignored);
        self::assertSame(1, $this->database()->table('user_roles')->where('user_id', $userId)->count());
    }
}
