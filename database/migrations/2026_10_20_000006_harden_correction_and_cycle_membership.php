<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_correction_lines', fn (Blueprint $t) => $t->unique(['id', 'product_id'], 'correction_line_product_uq'));
        Schema::table('inventory_correction_line_units', fn (Blueprint $t) => $t->foreign(['correction_line_id', 'product_id'], 'correction_unit_line_product_fk')->references(['id', 'product_id'])->on('inventory_correction_lines')->restrictOnDelete());
        Schema::table('loan_custody_cycles', fn (Blueprint $t) => $t->foreign(['previous_cycle_id', 'loan_item_id', 'individual_unit_id'], 'cycle_previous_identity_fk')->references(['id', 'loan_item_id', 'individual_unit_id'])->on('loan_custody_cycles')->restrictOnDelete());
        Schema::table('loan_cycle_closures', function (Blueprint $t) {
            $t->unsignedBigInteger('loan_item_id')->nullable();
            $t->unsignedBigInteger('individual_unit_id')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
        });
        DB::statement('UPDATE loan_cycle_closures c JOIN loan_custody_cycles p ON p.id=c.cycle_id SET c.loan_item_id=p.loan_item_id,c.individual_unit_id=p.individual_unit_id,c.product_id=p.product_id');
        DB::statement('ALTER TABLE loan_cycle_closures MODIFY loan_item_id BIGINT UNSIGNED NOT NULL, MODIFY individual_unit_id BIGINT UNSIGNED NOT NULL, MODIFY product_id BIGINT UNSIGNED NOT NULL');
        Schema::table('loan_cycle_closures', function (Blueprint $t) {
            $t->foreign(['cycle_id', 'loan_item_id', 'individual_unit_id'], 'closure_cycle_identity_fk')->references(['id', 'loan_item_id', 'individual_unit_id'])->on('loan_custody_cycles')->restrictOnDelete();
            $t->foreign(['return_item_id', 'loan_item_id', 'individual_unit_id'], 'closure_return_identity_fk')->references(['id', 'loan_item_id', 'individual_unit_id'])->on('tool_return_items')->restrictOnDelete();
            $t->foreign(['correction_line_id', 'individual_unit_id', 'product_id'], 'closure_correction_identity_fk')->references(['correction_line_id', 'individual_unit_id', 'product_id'])->on('inventory_correction_line_units')->restrictOnDelete();
        });
        Schema::table('loan_custody_cycles', fn (Blueprint $t) => $t->foreign(['opening_correction_line_id', 'individual_unit_id', 'product_id'], 'cycle_correction_identity_fk')->references(['correction_line_id', 'individual_unit_id', 'product_id'])->on('inventory_correction_line_units')->restrictOnDelete());
    }

    public function down(): void
    {
        throw new RuntimeException('La pertenencia histórica no se retira.');
    }
};
