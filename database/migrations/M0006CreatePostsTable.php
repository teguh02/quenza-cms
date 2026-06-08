<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0006CreatePostsTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable();
            $table->foreignId('parent_id')->nullable();
            $table->string('title', 255);
            $table->string('slug', 190);
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->enum('post_type', ['post', 'page'])->default('post');
            $table->enum('status', ['draft', 'published', 'trash'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 255)->nullable();
            $table->string('og_image', 255)->nullable();
            $table->timestamps();
            $table->unique('slug', 'uq_qz_posts_slug');
            $table->index(['post_type', 'status'], 'idx_qz_posts_type_status');
            $table->index('published_at', 'idx_qz_posts_published_at');
            $table->foreign('author_id', 'fk_qz_posts_author_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('parent_id', 'fk_qz_posts_parent_id')->references('id')->on('posts')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('posts');
    }
}
