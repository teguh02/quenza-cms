<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0004CreateAuthAttemptsTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('auth_attempts', static function (Blueprint $table): void {
            $table->id();
            $table->string('throttle_key', 64);
            $table->string('action', 40);
            $table->string('ip_address', 64);
            $table->string('identifier', 190)->nullable();
            $table->integer('attempts')->unsigned()->default(1);
            $table->timestamp('last_attempt_at')->useCurrent();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
            $table->unique('throttle_key', 'uq_qz_auth_attempts_throttle_key');
            $table->index(['action', 'ip_address'], 'idx_qz_auth_attempts_action_ip');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('auth_attempts');
    }
}
