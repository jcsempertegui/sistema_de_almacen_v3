<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_custody_cycles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('loan_item_id');
            $t->unsignedBigInteger('individual_unit_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('opening_loan_item_id')->nullable()->unique();
            $t->unsignedBigInteger('opening_correction_line_id')->nullable()->unique();
            $t->foreignId('previous_cycle_id')->nullable()->unique()->constrained('loan_custody_cycles')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['id', 'loan_item_id', 'individual_unit_id'], 'cycle_identity_uq');
            $t->foreign(['loan_item_id', 'individual_unit_id', 'product_id'], 'cycle_loan_identity_fk')->references(['id', 'individual_unit_id', 'product_id'])->on('tool_loan_items')->restrictOnDelete();
            $t->foreign('opening_loan_item_id')->references('loan_item_id')->on('inventory_movements')->restrictOnDelete();
            $t->foreign('opening_correction_line_id')->references('correction_line_id')->on('inventory_movements')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE loan_custody_cycles ADD CHECK ((opening_loan_item_id IS NOT NULL)+(opening_correction_line_id IS NOT NULL)=1), ADD CHECK (opening_loan_item_id IS NULL OR opening_loan_item_id=loan_item_id), ADD CHECK ((previous_cycle_id IS NULL AND opening_loan_item_id IS NOT NULL) OR (previous_cycle_id IS NOT NULL AND opening_correction_line_id IS NOT NULL))');
        Schema::create('loan_cycle_closures', function (Blueprint $t) {
            $t->unsignedBigInteger('cycle_id')->primary();
            $t->unsignedBigInteger('return_item_id')->nullable()->unique();
            $t->unsignedBigInteger('correction_line_id')->nullable()->unique();
            $t->foreignId('regularization_authorization_id')->nullable()->unique()->constrained('return_regularization_authorizations')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('cycle_id')->references('id')->on('loan_custody_cycles')->restrictOnDelete();
            $t->foreign('return_item_id')->references('return_item_id')->on('inventory_movements')->restrictOnDelete();
            $t->foreign('correction_line_id')->references('correction_line_id')->on('inventory_movements')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE loan_cycle_closures ADD CHECK ((return_item_id IS NOT NULL)+(correction_line_id IS NOT NULL)=1)');
        Schema::table('tool_return_items', function (Blueprint $t) {
            $t->unsignedBigInteger('custody_cycle_id')->nullable();
            $t->foreign(['custody_cycle_id', 'loan_item_id', 'individual_unit_id'], 'return_cycle_identity_fk')->references(['id', 'loan_item_id', 'individual_unit_id'])->on('loan_custody_cycles')->restrictOnDelete();
        });
        Schema::table('individual_unit_custodies', function (Blueprint $t) {
            $t->unsignedBigInteger('current_cycle_id')->nullable()->unique();
            $t->foreign(['current_cycle_id', 'current_loan_item_id', 'individual_unit_id'], 'custody_current_cycle_fk')->references(['id', 'loan_item_id', 'individual_unit_id'])->on('loan_custody_cycles')->restrictOnDelete();
        });
        // Only derive new records from existing physical loan/return movements.
        DB::transaction(function () {
            foreach (DB::table('tool_loan_items')->join('inventory_movements', 'inventory_movements.loan_item_id', '=', 'tool_loan_items.id')->select('tool_loan_items.*', 'inventory_movements.posted_at')->orderBy('tool_loan_items.id')->get() as $item) {
                $id = DB::table('loan_custody_cycles')->insertGetId(['loan_item_id' => $item->id, 'individual_unit_id' => $item->individual_unit_id, 'product_id' => $item->product_id, 'opening_loan_item_id' => $item->id, 'created_at' => $item->posted_at]);
                $closed = DB::table('loan_item_settlements')->where('loan_item_id', $item->id)->first();
                if ($closed) {
                    DB::table('loan_cycle_closures')->insert(['cycle_id' => $id, 'return_item_id' => $closed->return_item_id, 'regularization_authorization_id' => $closed->regularization_authorization_id, 'created_at' => $closed->created_at]);
                }
                DB::table('tool_return_items')->where('loan_item_id', $item->id)->update(['custody_cycle_id' => $id]);
                DB::table('individual_unit_custodies')->where('current_loan_item_id', $item->id)->update(['current_cycle_id' => $id]);
            }
        });
        DB::statement('ALTER TABLE individual_unit_custodies ADD CONSTRAINT custody_cycle_presence CHECK ((current_cycle_id IS NULL AND current_loan_item_id IS NULL) OR (current_cycle_id IS NOT NULL AND current_loan_item_id IS NOT NULL))');
    }

    public function down(): void
    {
        throw new RuntimeException('Los ciclos y cierres históricos se conservan.');
    }
};
