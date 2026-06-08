<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0009CreateMediaTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('media', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploader_user_id')->nullable();
            $table->string('disk', 50)->default('local');
            $table->string('path', 255);
            $table->string('filename', 255);
            $table->string('mime_type', 120)->nullable();
            $table->bigInteger('size_bytes')->unsigned()->default(0);
            $table->integer('width')->unsigned()->nullable();
            $table->integer('height')->unsigned()->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->timestamps();
            $table->index('created_at', 'idx_qz_media_created_at');
            $table->foreign('uploader_user_id', 'fk_qz_media_uploader_user_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('media');
    }
}
