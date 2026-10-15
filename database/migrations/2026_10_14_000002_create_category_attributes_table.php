<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_identity')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->decimal('min_value', 18, 6)->nullable();
            $table->decimal('max_value', 18, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->unique(['category_id', 'attribute_id']);
            $table->unique(['id', 'category_id']);
            $table->index(['category_id', 'is_active', 'display_order'], 'category_attributes_listing_index');
        });
        DB::statement('ALTER TABLE category_attributes ADD CONSTRAINT category_attributes_flags_check CHECK (is_required IN (0, 1) AND is_identity IN (0, 1) AND is_active IN (0, 1)), ADD CONSTRAINT category_attributes_bounds_check CHECK (min_value IS NULL OR max_value IS NULL OR min_value <= max_value)');
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión de configuraciones requiere preservar sus referencias históricas mediante un procedimiento autorizado.');
    }
};
