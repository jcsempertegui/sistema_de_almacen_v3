<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $t) {
            $t->unsignedBigInteger('correction_line_id')->nullable()->unique();
            $t->string('correction_kind', 12)->nullable();
            $t->unsignedBigInteger('compensated_movement_id')->nullable()->unique();
            $t->foreign(['correction_line_id', 'product_id', 'location_id', 'direction', 'quantity', 'correction_kind'], 'movement_correction_exact_fk')->references(['id', 'product_id', 'location_id', 'direction', 'quantity', 'kind'])->on('inventory_correction_lines')->restrictOnDelete();
            $t->foreign(['correction_line_id', 'compensated_movement_id'], 'movement_compensation_line_fk')->references(['id', 'original_movement_id'])->on('inventory_correction_lines')->restrictOnDelete();
            $t->foreign('compensated_movement_id')->references('id')->on('inventory_movements')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE inventory_movements DROP CONSTRAINT movement_one_source, ADD CONSTRAINT movement_one_source CHECK ((document_line_id IS NOT NULL)+(delivery_line_id IS NOT NULL)+(loan_item_id IS NOT NULL)+(return_item_id IS NOT NULL)+(correction_line_id IS NOT NULL)=1), ADD CONSTRAINT movement_correction_metadata CHECK ((correction_line_id IS NULL AND correction_kind IS NULL AND compensated_movement_id IS NULL) OR (correction_line_id IS NOT NULL AND correction_kind IS NOT NULL AND ((correction_kind='adjustment' AND compensated_movement_id IS NULL) OR (correction_kind='compensation' AND compensated_movement_id IS NOT NULL))))");
        Schema::table('individual_unit_positions', function (Blueprint $t) {
            $t->unsignedBigInteger('last_correction_line_id')->nullable();
            $t->foreign(['last_correction_line_id', 'individual_unit_id', 'product_id'], 'position_correction_identity_fk')->references(['correction_line_id', 'individual_unit_id', 'product_id'])->on('inventory_correction_line_units')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE individual_unit_positions DROP CONSTRAINT position_one_source, ADD CONSTRAINT position_one_source CHECK ((last_document_line_id IS NOT NULL)+(last_delivery_line_id IS NOT NULL)+(last_loan_item_id IS NOT NULL)+(last_return_item_id IS NOT NULL)+(last_correction_line_id IS NOT NULL)=1)');
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva el historial único de correcciones.');
    }
};
