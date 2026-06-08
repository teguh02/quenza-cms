<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0002CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 40);
            $table->string('full_name', 120);
            $table->string('email', 190);
            $table->string('password_hash', 255);
            $table->string('locale', 10)->default('id');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->integer('failed_login_attempts')->unsigned()->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique('username', 'uq_qz_users_username');
            $table->unique('email', 'uq_qz_users_email');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('users');
    }
}
