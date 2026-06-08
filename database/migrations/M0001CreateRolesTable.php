<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0001CreateRolesTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(1);
            $table->timestamps();
            $table->unique('slug', 'uq_qz_roles_slug');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('roles');
    }
}
