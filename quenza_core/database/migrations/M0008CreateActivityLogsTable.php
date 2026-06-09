<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0008CreateActivityLogsTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('activity_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 100)->nullable();
            $table->foreignId('subject_id')->nullable();
            $table->text('description');
            $table->timestamps();
            $table->index('created_at', 'idx_qz_activity_logs_created_at');
            $table->foreign('actor_user_id', 'fk_qz_activity_logs_actor_user_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('activity_logs');
    }
}
