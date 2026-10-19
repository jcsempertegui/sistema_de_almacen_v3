<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('individual_units', fn (Blueprint $t) => $t->unique(['id', 'product_id'], 'individual_units_id_product_unique'));
        Schema::create('inventory_documents', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('kind', 12);
            $t->string('status', 12)->default('draft');
            $t->date('document_date');
            $t->date('cutoff_date')->nullable();
            $t->string('counterparty_id', 80)->nullable();
            $t->string('counterparty_name', 200)->nullable();
            $t->string('external_reference', 100)->nullable();
            $t->text('notes')->nullable();
            $t->string('source', 8)->default('manual');
            $t->char('source_sha256', 64)->nullable()->index();
            $t->unsignedInteger('lock_version')->default(1);
            foreach (['created_by', 'updated_by', 'confirmed_by', 'cancelled_by'] as $column) {
                $t->foreignId($column)->nullable()->constrained('users')->restrictOnDelete();
            }
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancellation_reason', 500)->nullable();
            $t->timestamps();
            $t->index(['kind', 'counterparty_id', 'external_reference'], 'inventory_documents_external_index');
            $t->index(['kind', 'status', 'document_date'], 'inventory_documents_filter_index');
            $t->unique(['id', 'kind'], 'inventory_documents_id_kind_unique');
        });
        DB::statement("ALTER TABLE inventory_documents ADD CONSTRAINT inv_doc_kind_check CHECK (kind IN ('receipt','issue','opening')), ADD CONSTRAINT inv_doc_status_check CHECK (status IN ('draft','confirmed','cancelled')), ADD CONSTRAINT inv_doc_source_check CHECK (source IN ('manual','csv') AND (source <> 'csv' OR (kind = 'opening' AND source_sha256 IS NOT NULL))), ADD CONSTRAINT inv_doc_version_check CHECK (lock_version > 0), ADD CONSTRAINT inv_doc_metadata_check CHECK ((kind = 'opening' AND cutoff_date IS NOT NULL) OR (kind <> 'opening' AND counterparty_id IS NOT NULL AND CHAR_LENGTH(TRIM(counterparty_id)) > 0 AND counterparty_name IS NOT NULL AND CHAR_LENGTH(TRIM(counterparty_name)) > 0)), ADD CONSTRAINT inv_doc_confirmation_check CHECK ((status = 'confirmed' AND confirmed_at IS NOT NULL AND confirmed_by IS NOT NULL) OR (status <> 'confirmed' AND confirmed_at IS NULL AND confirmed_by IS NULL)), ADD CONSTRAINT inv_doc_cancellation_check CHECK ((status = 'cancelled' AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL AND cancellation_reason IS NOT NULL) OR (status <> 'cancelled' AND cancelled_at IS NULL AND cancelled_by IS NULL))");
        Schema::create('inventory_document_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained('inventory_documents')->restrictOnDelete();
            $t->unsignedSmallInteger('line_number');
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('location_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 18, 6);
            $t->foreignId('measurement_unit_id')->constrained()->restrictOnDelete();
            $t->string('unit_symbol', 20)->collation('utf8mb4_bin');
            $t->unsignedTinyInteger('decimal_places');
            $t->string('control_mode', 12);
            $t->unique(['document_id', 'line_number'], 'inv_lines_number_unique');
            $t->unique(['document_id', 'product_id', 'location_id'], 'inv_lines_pair_unique');
            $t->unique(['id', 'document_id', 'product_id'], 'inv_lines_document_product_unique');
            $t->unique(['id', 'product_id', 'location_id'], 'inv_lines_product_location_unique');
            $t->foreign(['product_id', 'location_id'], 'inv_lines_pair_fk')->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE inventory_document_lines ADD CONSTRAINT inv_line_quantity_check CHECK (quantity > 0), ADD CONSTRAINT inv_line_precision_check CHECK (decimal_places <= 6 AND quantity = ROUND(quantity, decimal_places)), ADD CONSTRAINT inv_line_mode_check CHECK (control_mode IN ('quantity','individual') AND (control_mode <> 'individual' OR (decimal_places = 0 AND quantity = FLOOR(quantity)))), ADD CONSTRAINT inv_line_number_check CHECK (line_number > 0)");
        Schema::create('inventory_document_line_units', function (Blueprint $t) {
            $t->unsignedBigInteger('document_line_id');
            $t->unsignedBigInteger('document_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('individual_unit_id');
            $t->primary(['document_line_id', 'individual_unit_id'], 'inv_line_units_primary');
            $t->unique(['document_id', 'individual_unit_id'], 'inv_line_units_document_unique');
            $t->foreign(['document_line_id', 'document_id', 'product_id'], 'inv_line_units_line_fk')->references(['id', 'document_id', 'product_id'])->on('inventory_document_lines')->restrictOnDelete();
            $t->foreign(['individual_unit_id', 'product_id'], 'inv_line_units_product_fk')->references(['id', 'product_id'])->on('individual_units')->restrictOnDelete();
        });
        Schema::create('stock_positions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('location_id');
            $t->decimal('quantity', 18, 6)->default(0);
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
            $t->unique(['product_id', 'location_id'], 'stock_positions_pair_unique');
            $t->foreign(['product_id', 'location_id'], 'stock_positions_pair_fk')->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE stock_positions ADD CONSTRAINT stock_quantity_check CHECK (quantity >= 0), ADD CONSTRAINT stock_version_check CHECK (lock_version > 0)');
        Schema::create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('document_line_id')->unique();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('location_id');
            $t->string('direction', 3);
            $t->decimal('quantity', 18, 6);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('posted_at');
            $t->foreign(['document_line_id', 'product_id', 'location_id'], 'inv_movements_line_fk')->references(['id', 'product_id', 'location_id'])->on('inventory_document_lines')->restrictOnDelete();
            $t->foreign(['product_id', 'location_id'], 'inv_movements_stock_fk')->references(['product_id', 'location_id'])->on('stock_positions')->restrictOnDelete();
            $t->index(['product_id', 'posted_at'], 'inv_movements_history_index');
        });
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inv_movement_direction_check CHECK (direction IN ('in','out')), ADD CONSTRAINT inv_movement_quantity_check CHECK (quantity > 0)");
        Schema::create('individual_unit_positions', function (Blueprint $t) {
            $t->unsignedBigInteger('individual_unit_id')->primary();
            $t->unsignedBigInteger('product_id');
            $t->boolean('is_present');
            $t->unsignedBigInteger('location_id')->nullable();
            $t->unsignedBigInteger('last_document_line_id');
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
            $t->foreign(['individual_unit_id', 'product_id'], 'inv_unit_position_product_fk')->references(['id', 'product_id'])->on('individual_units')->restrictOnDelete();
            $t->foreign(['product_id', 'location_id'], 'inv_unit_position_location_fk')->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete();
            $t->foreign(['last_document_line_id', 'individual_unit_id'], 'inv_unit_position_line_fk')->references(['document_line_id', 'individual_unit_id'])->on('inventory_document_line_units')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE individual_unit_positions ADD CONSTRAINT inv_unit_presence_check CHECK ((is_present = 1 AND location_id IS NOT NULL) OR (is_present = 0 AND location_id IS NULL)), ADD CONSTRAINT inv_unit_version_check CHECK (lock_version > 0)');
        Schema::create('initial_stock_products', function (Blueprint $t) {
            $t->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('inventory_document_id');
            $t->string('kind', 12)->default('opening');
            $t->foreign(['inventory_document_id', 'kind'], 'initial_stock_opening_fk')->references(['id', 'kind'])->on('inventory_documents')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE initial_stock_products ADD CONSTRAINT initial_stock_kind_check CHECK (kind = 'opening')");
    }

    public function down(): void
    {
        throw new RuntimeException('El inventario confirmado no se elimina por rollback. Requiere recuperación revisada y autorizada.');
    }
};
