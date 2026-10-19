<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_units', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 64)->unique('individual_units_identifier_unique');
            $table->foreignId('product_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('serial_number', 100)->nullable()->collation('utf8mb4_bin');
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
            $table->unique(['product_id', 'serial_number'], 'individual_units_product_serial_unique');
            $table->index(['product_id', 'location_id'], 'individual_units_product_location');
            $table->index(['product_id', 'is_active'], 'individual_units_product_active');
            $table->index(['location_id', 'is_active'], 'individual_units_location_active');
            $table->foreign(['product_id', 'location_id'], 'individual_units_product_location_fk')
                ->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete()->restrictOnUpdate();
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        DB::statement('ALTER TABLE individual_units ADD CONSTRAINT individual_units_active_check CHECK (is_active IN (0,1)), ADD CONSTRAINT individual_units_version_check CHECK (lock_version > 0), ADD CONSTRAINT individual_units_identifier_check CHECK (CHAR_LENGTH(TRIM(identifier)) > 0), ADD CONSTRAINT individual_units_serial_check CHECK (serial_number IS NULL OR CHAR_LENGTH(TRIM(serial_number)) > 0)');
    }

    public function down(): void
    {
        throw new RuntimeException('No se eliminan identidades ni referencias históricas de unidades individualizadas.');
    }
};
