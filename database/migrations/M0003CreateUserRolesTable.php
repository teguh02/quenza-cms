<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0003CreateUserRolesTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('user_roles', static function (Blueprint $table): void {
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['user_id', 'role_id'], 'pk_qz_user_roles');
            $table->index('role_id', 'idx_qz_user_roles_role_id');
            $table->foreign('user_id', 'fk_qz_user_roles_user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('role_id', 'fk_qz_user_roles_role_id')->references('id')->on('roles')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('user_roles');
    }
}
