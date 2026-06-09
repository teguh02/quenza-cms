<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0011CreateMenusTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('menus', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('location', 80)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique('location', 'uq_qz_menus_location');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('menus');
    }
}
