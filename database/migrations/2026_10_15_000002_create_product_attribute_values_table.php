<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('category_attribute_id');
            $table->string('value_text', 255)->nullable();
            $table->decimal('value_decimal', 18, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->unique(['product_id', 'category_attribute_id'], 'pav_product_attribute_unique');
            $table->index(['product_id', 'category_id'], 'pav_product_category_index');
            $table->index(['category_attribute_id', 'category_id'], 'pav_attribute_category_index');
            $table->index(['product_id', 'is_active'], 'pav_product_active_index');
            $table->index(['category_attribute_id', 'value_decimal'], 'pav_attribute_decimal_index');
            $table->foreign(['product_id', 'category_id'], 'pav_product_category_fk')->references(['id', 'category_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['category_attribute_id', 'category_id'], 'pav_attribute_category_fk')->references(['id', 'category_id'])->on('category_attributes')->restrictOnDelete()->restrictOnUpdate();
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        DB::statement('ALTER TABLE product_attribute_values ADD CONSTRAINT pav_one_value_check CHECK ((value_text IS NOT NULL)+(value_decimal IS NOT NULL)+(value_boolean IS NOT NULL)+(value_date IS NOT NULL)=1), ADD CONSTRAINT pav_flags_check CHECK (is_active IN (0,1) AND (value_boolean IS NULL OR value_boolean IN (0,1)))');
    }

    public function down(): void
    {
        throw new RuntimeException('No se eliminan valores técnicos históricos.');
    }
};
