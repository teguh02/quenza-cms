<?php
declare(strict_types=1);

namespace Database\Seeders;

use Quenza\Core\Database\Seeder;
use Quenza\Core\Enums\UserStatus;
use Quenza\Core\Security\Security;
use Quenza\Core\Support\Str;
use Quenza\Core\Support\Env;
use DateTimeImmutable;
use RuntimeException;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $fullName = trim(Env::string('QZ_ADMIN_NAME', ''));
        $email = trim(Env::string('QZ_ADMIN_EMAIL', ''));
        $password = Env::string('QZ_ADMIN_PASSWORD', '');

        // Admin bootstrap hanya dibuat jika environment eksplisit disediakan.
        if ($fullName === '' || $email === '' || $password === '') {
            return;
        }

        /** @var Security $security */
        $security = $this->app->get(Security::class);
        $passwordHash = $security->hashPassword($password);

        $roleId = $this->resolveAdminRoleId();
        $userId = $this->upsertAdminUser($fullName, $email, $passwordHash);

        $this->db()->updateOrInsert('user_roles', [
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    private function resolveAdminRoleId(): int
    {
        $role = $this->db()->table('roles')->where('slug', 'admin')->first();
        $roleId = $role['id'] ?? null;

        if (!is_numeric($roleId)) {
            throw new RuntimeException('Role admin tidak ditemukan. Jalankan RoleSeeder terlebih dahulu.');
        }

        return (int) $roleId;
    }

    private function upsertAdminUser(string $fullName, string $email, string $passwordHash): int
    {
        $username = $this->generateUsername($fullName, $email);
        $user = $this->db()->table('users')->where('email', $email)->first();
        $userId = $user['id'] ?? null;

        if (is_numeric($userId)) {
            $this->db()->update('users', [
                'username' => $username,
                'full_name' => $fullName,
                'password_hash' => $passwordHash,
                'locale' => (string) $this->app->config('app.locale', 'id'),
                'status' => UserStatus::Active->value,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => (int) $userId,
            ]);

            return (int) $userId;
        }

        return $this->db()->insertGetId('users', [
            'username' => $username,
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => $passwordHash,
            'locale' => (string) $this->app->config('app.locale', 'id'),
            'status' => UserStatus::Active->value,
        ]);
    }

    private function generateUsername(string $fullName, string $email): string
    {
        $seed = $fullName !== '' ? $fullName : (strstr($email, '@', true) ?: 'admin');
        $base = substr(Str::slug($seed, '_'), 0, 40);

        if ($base === '') {
            $base = 'admin';
        }

        $candidate = $base;
        $suffix = 1;

        while ($this->db()->table('users')->where('username', $candidate)->where('email', '!=', $email)->exists()) {
            $suffix++;
            $candidate = substr($base, 0, max(1, 40 - strlen((string) $suffix) - 1)) . '_' . $suffix;
        }

        return $candidate;
    }
}
