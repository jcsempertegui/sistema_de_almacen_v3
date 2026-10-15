<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_value_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_attribute_value_id');
            $table->foreign('product_attribute_value_id', 'pavr_value_fk')->references('id')->on('product_attribute_values')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('revision');
            $table->string('value_text', 255)->nullable();
            $table->decimal('value_decimal', 18, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('is_active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('created_at');
            $table->unique(['product_attribute_value_id', 'revision'], 'pavr_value_revision_unique');
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        DB::statement('ALTER TABLE product_attribute_value_revisions ADD CONSTRAINT pavr_one_value_check CHECK ((value_text IS NOT NULL)+(value_decimal IS NOT NULL)+(value_boolean IS NOT NULL)+(value_date IS NOT NULL)=1), ADD CONSTRAINT pavr_flags_check CHECK (is_active IN (0,1) AND (value_boolean IS NULL OR value_boolean IN (0,1))), ADD CONSTRAINT pavr_revision_check CHECK (revision>0)');
    }

    public function down(): void
    {
        throw new RuntimeException('No se eliminan revisiones históricas.');
    }
};
