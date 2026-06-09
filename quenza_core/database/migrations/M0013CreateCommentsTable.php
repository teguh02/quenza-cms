<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0013CreateCommentsTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('comments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id');
            $table->string('author_name', 100);
            $table->string('author_email', 150);
            $table->text('content');
            $table->string('status', 20)->default('pending'); // pending, approved, spam, trash
            $table->timestamps();

            $table->index('post_id', 'idx_qz_comments_post_id');
            $table->foreign('post_id', 'fk_qz_comments_post_id')
                ->references('id')
                ->on('posts')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('comments');
    }
}
