<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0005CreateCategoriesTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('categories', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 150);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique('slug', 'uq_qz_categories_slug');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('categories');
    }
}
