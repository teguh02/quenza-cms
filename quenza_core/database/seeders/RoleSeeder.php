<?php
declare(strict_types=1);

namespace Database\Seeders;

use Quenza\Core\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Role inti disediakan sejak awal agar autentikasi dan otorisasi punya baseline yang konsisten.
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Akses penuh ke seluruh fitur CMS.'],
            ['name' => 'Editor', 'slug' => 'editor', 'description' => 'Mengelola dan menerbitkan konten.'],
            ['name' => 'Author', 'slug' => 'author', 'description' => 'Membuat dan mengelola kontennya sendiri.'],
            ['name' => 'Subscriber', 'slug' => 'subscriber', 'description' => 'Akses terbatas sebagai pengguna terdaftar.'],
        ];

        foreach ($roles as $role) {
            $this->db()->updateOrInsert(
                'roles',
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_system' => 1,
                ],
            );
        }
    }
}
