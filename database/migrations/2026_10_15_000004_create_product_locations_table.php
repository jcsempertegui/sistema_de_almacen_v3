<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('location_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->unique(['product_id', 'location_id'], 'product_locations_pair_unique');
            $table->index(['location_id', 'is_active'], 'product_locations_location_active');
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        DB::statement('ALTER TABLE product_locations ADD CONSTRAINT product_locations_active_check CHECK (is_active IN (0,1))');
    }

    public function down(): void
    {
        throw new RuntimeException('No se eliminan asociaciones históricas con ubicaciones.');
    }
};
