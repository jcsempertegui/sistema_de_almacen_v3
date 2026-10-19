<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE individual_unit_positions MODIFY last_document_line_id BIGINT UNSIGNED NULL');
        Schema::table('individual_unit_positions', function (Blueprint $t) {
            foreach (['last_delivery_line_id', 'last_loan_item_id', 'last_return_item_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->foreign(['last_delivery_line_id', 'individual_unit_id', 'product_id'], 'position_delivery_fk')->references(['delivery_line_id', 'individual_unit_id', 'product_id'])->on('material_delivery_line_units')->restrictOnDelete();
            $t->foreign(['last_loan_item_id', 'individual_unit_id', 'product_id'], 'position_loan_fk')->references(['id', 'individual_unit_id', 'product_id'])->on('tool_loan_items')->restrictOnDelete();
            $t->foreign(['last_return_item_id', 'individual_unit_id', 'product_id', 'location_id'], 'position_return_fk')->references(['id', 'individual_unit_id', 'product_id', 'location_id'])->on('tool_return_items')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE individual_unit_positions ADD CONSTRAINT position_one_source CHECK ((last_document_line_id IS NOT NULL)+(last_delivery_line_id IS NOT NULL)+(last_loan_item_id IS NOT NULL)+(last_return_item_id IS NOT NULL)=1), ADD CONSTRAINT position_out CHECK ((last_delivery_line_id IS NULL AND last_loan_item_id IS NULL) OR is_present=0), ADD CONSTRAINT position_return CHECK (last_return_item_id IS NULL OR is_present=1)');
    }

    public function down(): void
    {
        throw new RuntimeException('Se conservan referencias de posición.');
    }
};
