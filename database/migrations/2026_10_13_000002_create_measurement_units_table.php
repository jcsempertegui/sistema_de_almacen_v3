<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('symbol', 20)->collation('utf8mb4_bin')->unique();
            $table->string('dimension', 20);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });
        DB::statement('ALTER TABLE measurement_units ADD CONSTRAINT measurement_units_precision_check CHECK (decimal_places BETWEEN 0 AND 6), ADD CONSTRAINT measurement_units_dimension_check CHECK (dimension IN (\'count\', \'length\', \'mass\', \'volume\', \'time\', \'other\')), ADD CONSTRAINT measurement_units_active_check CHECK (is_active IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_units');
    }
};
