<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_correction_lines', function (Blueprint $t) {
            $t->unique(['id', 'product_id', 'direction'], 'correction_position_direction_uq');
            $t->unique(['id', 'product_id', 'location_id', 'direction'], 'correction_position_location_uq');
        });
        Schema::table('individual_unit_positions', fn (Blueprint $t) => $t->string('last_correction_direction', 3)->nullable());
        DB::statement('UPDATE individual_unit_positions p JOIN inventory_correction_lines c ON c.id=p.last_correction_line_id SET p.last_correction_direction=c.direction');
        Schema::table('individual_unit_positions', function (Blueprint $t) {
            $t->foreign(['last_correction_line_id', 'product_id', 'last_correction_direction'], 'position_correction_direction_fk')->references(['id', 'product_id', 'direction'])->on('inventory_correction_lines')->restrictOnDelete();
            $t->foreign(['last_correction_line_id', 'product_id', 'location_id', 'last_correction_direction'], 'position_correction_location_fk')->references(['id', 'product_id', 'location_id', 'direction'])->on('inventory_correction_lines')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE individual_unit_positions ADD CHECK ((last_correction_line_id IS NULL AND last_correction_direction IS NULL) OR (last_correction_line_id IS NOT NULL AND last_correction_direction IS NOT NULL AND ((last_correction_direction='in' AND is_present=1) OR (last_correction_direction='out' AND is_present=0))))");
    }

    public function down(): void
    {
        throw new RuntimeException('La posición conserva su fuente y dirección física.');
    }
};
