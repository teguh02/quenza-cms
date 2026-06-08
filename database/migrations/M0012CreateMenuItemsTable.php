<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0012CreateMenuItemsTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('menu_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id');
            $table->foreignId('parent_id')->nullable();
            $table->foreignId('linked_post_id')->nullable();
            $table->string('label', 150);
            $table->string('url', 255)->nullable();
            $table->enum('type', ['custom', 'post', 'page'])->default('custom');
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->string('target', 20)->default('_self');
            $table->string('css_class', 120)->nullable();
            $table->timestamps();
            $table->index(['menu_id', 'sort_order'], 'idx_qz_menu_items_menu_sort');
            $table->foreign('menu_id', 'fk_qz_menu_items_menu_id')->references('id')->on('menus')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('parent_id', 'fk_qz_menu_items_parent_id')->references('id')->on('menu_items')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('linked_post_id', 'fk_qz_menu_items_linked_post_id')->references('id')->on('posts')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('menu_items');
    }
}
