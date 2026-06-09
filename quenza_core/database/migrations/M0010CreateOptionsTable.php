<?php
declare(strict_types=1);

namespace Database\Migrations;

use Quenza\Core\Database\Migration;
use Quenza\Core\Database\Schema\Blueprint;

final class M0010CreateOptionsTable extends Migration
{
    public function up(): void
    {
        $this->schema()->create('options', static function (Blueprint $table): void {
            $table->id();
            $table->string('option_name', 190);
            $table->longText('option_value')->nullable();
            $table->boolean('autoload')->default(1);
            $table->timestamps();
            $table->unique('option_name', 'uq_qz_options_option_name');
            $table->index('autoload', 'idx_qz_options_autoload');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('options');
    }
}
