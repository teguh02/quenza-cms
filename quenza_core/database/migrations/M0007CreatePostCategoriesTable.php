<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0007CreatePostCategoriesTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('post_categories', static function (Blueprint $table): void {
            $table->foreignId('post_id');
            $table->foreignId('category_id');
            $table->primary(['post_id', 'category_id'], 'pk_qz_post_categories');
            $table->index('category_id', 'idx_qz_post_categories_category_id');
            $table->foreign('post_id', 'fk_qz_post_categories_post_id')->references('id')->on('posts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('category_id', 'fk_qz_post_categories_category_id')->references('id')->on('categories')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('post_categories');
    }
}
