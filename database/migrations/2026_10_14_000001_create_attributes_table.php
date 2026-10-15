<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('data_type', 16)->collation('utf8mb4_bin');
            $table->foreignId('measurement_unit_id')->nullable()->constrained('measurement_units')->restrictOnDelete()->restrictOnUpdate();
            $table->string('help_text', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });
        DB::statement("ALTER TABLE attributes ADD CONSTRAINT attributes_type_check CHECK (data_type IN ('text', 'decimal', 'boolean', 'date')), ADD CONSTRAINT attributes_unit_check CHECK (measurement_unit_id IS NULL OR data_type = 'decimal'), ADD CONSTRAINT attributes_active_check CHECK (is_active IN (0, 1))");
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión de atributos requiere preservar sus referencias históricas mediante un procedimiento autorizado.');
    }
};
