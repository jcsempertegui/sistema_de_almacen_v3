<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retain every historical value and the original composite FK.
        DB::statement('ALTER TABLE inventory_movements MODIFY document_line_id BIGINT UNSIGNED NULL');
        foreach (['delivery_line_id' => 'material_delivery_lines', 'loan_item_id' => 'tool_loan_items', 'return_item_id' => 'tool_return_items'] as $column => $parent) {
            Schema::table('inventory_movements', function (Blueprint $t) use ($column, $parent) {
                $t->unsignedBigInteger($column)->nullable()->unique();
                $t->foreign([$column, 'product_id', 'location_id'], 'movement_'.$column.'_fk')->references(['id', 'product_id', 'location_id'])->on($parent)->restrictOnDelete();
            });
        }
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT movement_one_source CHECK ((document_line_id IS NOT NULL)+(delivery_line_id IS NOT NULL)+(loan_item_id IS NOT NULL)+(return_item_id IS NOT NULL)=1), ADD CONSTRAINT movement_out_source CHECK ((delivery_line_id IS NULL AND loan_item_id IS NULL) OR direction='out'), ADD CONSTRAINT movement_return_in CHECK (return_item_id IS NULL OR direction='in'), ADD CONSTRAINT movement_single_tool CHECK ((loan_item_id IS NULL AND return_item_id IS NULL) OR quantity=1)");
    }

    public function down(): void
    {
        throw new RuntimeException('No se retiran fuentes de movimientos históricos.');
    }
};
