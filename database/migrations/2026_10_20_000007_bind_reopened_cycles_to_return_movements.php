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
            $t->unique(['correction_line_id', 'compensated_movement_id'], 'movement_reversal_origin_uq');
            $t->unique(['id', 'return_item_id'], 'movement_return_origin_uq');
        });
        Schema::table('loan_cycle_closures', function (Blueprint $t) {
            $t->unique(['cycle_id', 'return_item_id'], 'closure_return_cycle_uq');
            $t->foreign(['regularization_authorization_id', 'return_item_id'], 'closure_regularization_item_fk')->references(['id', 'return_item_id'])->on('return_regularization_authorizations')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE loan_cycle_closures ADD CHECK (regularization_authorization_id IS NULL OR return_item_id IS NOT NULL)');
        Schema::table('loan_custody_cycles', function (Blueprint $t) {
            $t->unsignedBigInteger('reversed_return_movement_id')->nullable();
            $t->unsignedBigInteger('source_return_item_id')->nullable();
        });
        DB::statement('UPDATE loan_custody_cycles c JOIN inventory_movements m ON m.correction_line_id=c.opening_correction_line_id JOIN inventory_movements r ON r.id=m.compensated_movement_id SET c.reversed_return_movement_id=r.id,c.source_return_item_id=r.return_item_id');
        Schema::table('loan_custody_cycles', function (Blueprint $t) {
            $t->foreign(['opening_correction_line_id', 'reversed_return_movement_id'], 'cycle_reversal_movement_fk')->references(['correction_line_id', 'compensated_movement_id'])->on('inventory_movements')->restrictOnDelete();
            $t->foreign(['reversed_return_movement_id', 'source_return_item_id'], 'cycle_original_return_movement_fk')->references(['id', 'return_item_id'])->on('inventory_movements')->restrictOnDelete();
            $t->foreign(['source_return_item_id', 'loan_item_id', 'individual_unit_id'], 'cycle_original_return_identity_fk')->references(['id', 'loan_item_id', 'individual_unit_id'])->on('tool_return_items')->restrictOnDelete();
            $t->foreign(['previous_cycle_id', 'source_return_item_id'], 'cycle_previous_return_fk')->references(['cycle_id', 'return_item_id'])->on('loan_cycle_closures')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE loan_custody_cycles ADD CHECK ((opening_loan_item_id IS NOT NULL AND reversed_return_movement_id IS NULL AND source_return_item_id IS NULL) OR (opening_correction_line_id IS NOT NULL AND reversed_return_movement_id IS NOT NULL AND source_return_item_id IS NOT NULL))');
    }

    public function down(): void
    {
        throw new RuntimeException('Las reaperturas conservan sus pruebas físicas.');
    }
};
