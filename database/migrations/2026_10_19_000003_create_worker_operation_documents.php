<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['material_deliveries', 'tool_loans', 'tool_returns'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
                if ($table === 'tool_returns') {
                    $t->foreignId('loan_id')->constrained('tool_loans')->restrictOnDelete();
                    $t->unique(['id', 'loan_id'], 'returns_id_loan_uq');
                    $t->foreign(['loan_id', 'worker_id'], 'returns_worker_fk')->references(['id', 'worker_id'])->on('tool_loans')->restrictOnDelete();
                }
                if ($table === 'tool_loans') {
                    $t->date('due_date')->nullable();
                    $t->unique(['id', 'worker_id'], 'loans_id_worker_uq');
                }
                $t->date('document_date');
                $t->string('status', 12)->default('draft');
                $t->text('worker_snapshot')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedInteger('lock_version')->default(1);
                foreach (['created_by', 'updated_by', 'confirmed_by', 'cancelled_by'] as $c) {
                    $t->foreignId($c)->nullable()->constrained('users')->restrictOnDelete();
                }
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->string('cancellation_reason', 500)->nullable();
                $t->timestamps();
                $t->index(['worker_id', 'status', 'document_date']);
            });
            DB::statement("ALTER TABLE {$table} ADD CHECK (status IN ('draft','confirmed','cancelled')), ADD CHECK (lock_version > 0), ADD CHECK ((status = 'confirmed' AND confirmed_by IS NOT NULL AND confirmed_at IS NOT NULL AND worker_snapshot IS NOT NULL) OR (status <> 'confirmed' AND confirmed_by IS NULL AND confirmed_at IS NULL)), ADD CHECK ((status = 'cancelled' AND cancelled_by IS NOT NULL AND cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL) OR (status <> 'cancelled' AND cancelled_by IS NULL AND cancelled_at IS NULL))");
        }
        Schema::create('material_delivery_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained('material_deliveries')->restrictOnDelete();
            $t->unsignedSmallInteger('line_number');
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('location_id');
            $t->decimal('quantity', 18, 6);
            $t->foreignId('measurement_unit_id')->constrained()->restrictOnDelete();
            $t->string('unit_symbol', 20)->collation('utf8mb4_bin');
            $t->unsignedTinyInteger('decimal_places');
            $t->string('control_mode', 12);
            $t->unique(['delivery_id', 'line_number'], 'delivery_number_uq');
            $t->unique(['delivery_id', 'product_id', 'location_id'], 'delivery_pair_uq');
            $t->unique(['id', 'product_id', 'location_id'], 'delivery_origin_uq');
            $t->unique(['id', 'delivery_id', 'product_id'], 'delivery_membership_uq');
            $t->foreign(['product_id', 'location_id'], 'delivery_pair_fk')->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE material_delivery_lines ADD CHECK (quantity > 0 AND decimal_places <= 6 AND quantity = ROUND(quantity, decimal_places)), ADD CHECK (control_mode IN ('quantity','individual') AND (control_mode <> 'individual' OR (decimal_places = 0 AND quantity = FLOOR(quantity))))");
        Schema::create('material_delivery_line_units', function (Blueprint $t) {
            $t->unsignedBigInteger('delivery_line_id');
            $t->unsignedBigInteger('delivery_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('individual_unit_id');
            $t->primary(['delivery_line_id', 'individual_unit_id'], 'delivery_units_pk');
            $t->unique(['delivery_id', 'individual_unit_id'], 'delivery_identity_uq');
            $t->unique(['delivery_line_id', 'individual_unit_id', 'product_id'], 'delivery_unit_product_uq');
            $t->foreign(['delivery_line_id', 'delivery_id', 'product_id'], 'delivery_unit_line_fk')->references(['id', 'delivery_id', 'product_id'])->on('material_delivery_lines')->restrictOnDelete();
            $t->foreign(['individual_unit_id', 'product_id'], 'delivery_unit_product_fk')->references(['id', 'product_id'])->on('individual_units')->restrictOnDelete();
        });
        Schema::create('tool_loan_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained('tool_loans')->restrictOnDelete();
            $t->unsignedBigInteger('individual_unit_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('location_id');
            $t->foreignId('measurement_unit_id')->constrained()->restrictOnDelete();
            $t->string('unit_symbol', 20)->collation('utf8mb4_bin');
            $t->unique(['loan_id', 'individual_unit_id'], 'loan_identity_uq');
            $t->unique(['id', 'product_id', 'location_id'], 'loan_origin_uq');
            $t->unique(['id', 'individual_unit_id', 'product_id'], 'loan_identity_product_uq');
            $t->unique(['id', 'loan_id', 'individual_unit_id', 'product_id'], 'loan_membership_uq');
            $t->foreign(['individual_unit_id', 'product_id'], 'loan_unit_fk')->references(['id', 'product_id'])->on('individual_units')->restrictOnDelete();
            $t->foreign(['product_id', 'location_id'], 'loan_pair_fk')->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete();
        });
        Schema::create('tool_return_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('return_id');
            $t->unsignedBigInteger('loan_id');
            $t->unsignedBigInteger('loan_item_id');
            $t->unsignedBigInteger('individual_unit_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('location_id');
            $t->unique(['return_id', 'loan_item_id'], 'return_identity_uq');
            $t->unique(['id', 'product_id', 'location_id'], 'return_origin_uq');
            $t->unique(['id', 'individual_unit_id', 'product_id', 'location_id'], 'return_position_uq');
            $t->unique(['id', 'loan_item_id', 'individual_unit_id'], 'return_settlement_uq');
            $t->foreign(['return_id', 'loan_id'], 'return_header_fk')->references(['id', 'loan_id'])->on('tool_returns')->restrictOnDelete();
            $t->foreign(['loan_item_id', 'loan_id', 'individual_unit_id', 'product_id'], 'return_loan_item_fk')->references(['id', 'loan_id', 'individual_unit_id', 'product_id'])->on('tool_loan_items')->restrictOnDelete();
            $t->foreign(['product_id', 'location_id'], 'return_pair_fk')->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Los documentos históricos requieren recuperación autorizada.');
    }
};
