<?php
declare(strict_types=1);

namespace Database\Seeders;

use Quenza\Core\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(OptionSeeder::class);
        $this->call(AdminUserSeeder::class);
    }
}
