<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code', 60)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('measurement_unit_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('brand', 100)->nullable();
            $table->string('manufacturer_reference', 100)->nullable();
            $table->string('control_mode', 16)->collation('utf8mb4_bin');
            $table->boolean('is_loanable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->unique(['id', 'category_id'], 'products_id_category_unique');
            $table->index(['category_id', 'measurement_unit_id', 'control_mode', 'is_active'], 'products_catalog_filter');
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_mode_check CHECK (control_mode IN ('quantity','individual')), ADD CONSTRAINT products_flags_check CHECK (is_active IN (0,1) AND is_loanable IN (0,1)), ADD CONSTRAINT products_loan_check CHECK (is_loanable=0 OR control_mode='individual'), ADD CONSTRAINT products_version_check CHECK (lock_version>0)");
    }

    public function down(): void
    {
        throw new RuntimeException('No se elimina el catálogo de productos ni su historial.');
    }
};
