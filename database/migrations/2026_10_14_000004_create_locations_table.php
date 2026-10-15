<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->string('type', 16)->collation('utf8mb4_bin');
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('is_storable')->default(true);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->index(['parent_id', 'is_active', 'type']);
            $table->index(['is_active', 'name']);
        });
        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_type_check CHECK (type IN ('sector', 'shelf', 'cabinet')), ADD CONSTRAINT locations_flags_check CHECK (is_active IN (0, 1) AND is_storable IN (0, 1))");
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión de ubicaciones requiere preservar sus referencias históricas mediante un procedimiento autorizado.');
    }
};
