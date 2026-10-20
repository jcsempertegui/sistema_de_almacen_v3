<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->unique(['id', 'product_id', 'location_id', 'direction', 'quantity'], 'movement_exact_origin_uq'));
        Schema::create('inventory_corrections', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('kind', 12);
            $t->string('status', 12)->default('draft');
            $t->date('document_date');
            $t->string('reason', 1000);
            $t->string('source_kind', 24)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->unsignedInteger('lock_version')->default(1);
            foreach (['created_by', 'updated_by', 'executed_by'] as $c) {
                $t->foreignId($c)->nullable()->constrained('users')->restrictOnDelete();
            }
            $t->timestamp('executed_at')->nullable();
            $t->timestamps();
            $t->unique(['id', 'kind'], 'correction_kind_uq');
            $t->index(['status', 'document_date']);
        });
        DB::statement("ALTER TABLE inventory_corrections ADD CHECK (kind IN ('adjustment','compensation')), ADD CHECK (status IN ('draft','requested','authorized','rejected','cancelled','executed')), ADD CHECK (CHAR_LENGTH(TRIM(reason)) > 0 AND lock_version > 0), ADD CHECK ((kind='adjustment' AND source_kind IS NULL AND source_id IS NULL) OR (kind='compensation' AND source_kind IN ('inventory-documents','deliveries','loans','returns','corrections') AND source_id IS NOT NULL)), ADD CHECK ((status='executed' AND executed_at IS NOT NULL AND executed_by IS NOT NULL) OR (status<>'executed' AND executed_at IS NULL AND executed_by IS NULL))");
        Schema::create('inventory_correction_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('correction_id');
            $t->string('kind', 12);
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('location_id');
            $t->string('direction', 3);
            $t->decimal('quantity', 18, 6);
            $t->foreignId('measurement_unit_id')->constrained()->restrictOnDelete();
            $t->string('unit_symbol', 20)->collation('utf8mb4_bin');
            $t->unsignedTinyInteger('decimal_places');
            $t->string('control_mode', 12);
            $t->decimal('observed_quantity', 18, 6);
            $t->unsignedInteger('observed_version');
            $t->decimal('counted_quantity', 18, 6)->nullable();
            $t->unsignedBigInteger('original_movement_id')->nullable();
            $t->string('original_direction', 3)->nullable();
            $t->unique(['id', 'product_id', 'location_id', 'direction', 'quantity', 'kind'], 'correction_exact_uq');
            $t->unique(['id', 'original_movement_id'], 'correction_original_uq');
            $t->unique(['correction_id', 'original_movement_id'], 'correction_original_once_uq');
            $t->foreign(['correction_id', 'kind'], 'correction_header_fk')->references(['id', 'kind'])->on('inventory_corrections')->restrictOnDelete();
            $t->foreign(['product_id', 'location_id'], 'correction_pair_fk')->references(['product_id', 'location_id'])->on('product_locations')->restrictOnDelete();
            $t->foreign(['original_movement_id', 'product_id', 'location_id', 'original_direction', 'quantity'], 'correction_original_fk')->references(['id', 'product_id', 'location_id', 'direction', 'quantity'])->on('inventory_movements')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE inventory_correction_lines ADD CHECK (direction IN ('in','out') AND quantity>0 AND observed_quantity>=0 AND decimal_places<=6 AND quantity=ROUND(quantity,decimal_places)), ADD CHECK (control_mode IN ('quantity','individual') AND (control_mode<>'individual' OR (decimal_places=0 AND quantity=FLOOR(quantity)))), ADD CHECK ((kind='adjustment' AND original_movement_id IS NULL AND original_direction IS NULL AND counted_quantity IS NOT NULL AND counted_quantity>=0 AND counted_quantity=ROUND(counted_quantity,decimal_places) AND ((direction='in' AND counted_quantity-observed_quantity=quantity) OR (direction='out' AND observed_quantity-counted_quantity=quantity))) OR (kind='compensation' AND original_movement_id IS NOT NULL AND original_direction IS NOT NULL AND original_direction IN ('in','out') AND original_direction<>direction AND counted_quantity IS NULL))");
        Schema::create('inventory_correction_line_units', function (Blueprint $t) {
            $t->unsignedBigInteger('correction_line_id');
            $t->unsignedBigInteger('individual_unit_id');
            $t->unsignedBigInteger('product_id');
            $t->primary(['correction_line_id', 'individual_unit_id'], 'correction_units_pk');
            $t->unique(['correction_line_id', 'individual_unit_id', 'product_id'], 'correction_units_product_uq');
            $t->foreign('correction_line_id')->references('id')->on('inventory_correction_lines')->restrictOnDelete();
            $t->foreign(['individual_unit_id', 'product_id'], 'correction_unit_product_fk')->references(['id', 'product_id'])->on('individual_units')->restrictOnDelete();
        });
        Schema::create('document_compensations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('correction_id')->unique()->constrained('inventory_corrections')->restrictOnDelete();
            foreach (['inventory_document_id' => 'inventory_documents', 'delivery_id' => 'material_deliveries', 'loan_id' => 'tool_loans', 'return_id' => 'tool_returns', 'target_correction_id' => 'inventory_corrections'] as $c => $parent) {
                $t->foreignId($c)->nullable()->unique()->constrained($parent)->restrictOnDelete();
            }
            $t->timestamp('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE document_compensations ADD CHECK ((inventory_document_id IS NOT NULL)+(delivery_id IS NOT NULL)+(loan_id IS NOT NULL)+(return_id IS NOT NULL)+(target_correction_id IS NOT NULL)=1), ADD CHECK (target_correction_id IS NULL OR target_correction_id<>correction_id)');
        Schema::create('correction_authorizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('correction_id')->constrained('inventory_corrections')->restrictOnDelete();
            $t->string('status', 12)->default('requested');
            $t->char('fingerprint', 64);
            $t->unsignedInteger('document_version');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('reason', 1000)->nullable();
            $t->timestamp('requested_at');
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();
        });
        DB::statement("ALTER TABLE correction_authorizations ADD CHECK (status IN ('requested','approved','rejected','revoked','used')), ADD CHECK (approved_by IS NULL OR approved_by<>requested_by), ADD CHECK (status NOT IN ('approved','used') OR (approved_by IS NOT NULL AND approved_at IS NOT NULL AND expires_at IS NOT NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason))>0)), ADD CHECK ((status='used' AND used_at IS NOT NULL) OR (status<>'used' AND used_at IS NULL))");
        Schema::create('correction_authorization_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('authorization_id')->constrained('correction_authorizations')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 12);
            $t->text('payload');
            $t->timestamp('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE correction_authorization_events ADD CHECK (action IN ('requested','approved','rejected','revoked','used'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Las correcciones históricas requieren recuperación autorizada.');
    }
};
