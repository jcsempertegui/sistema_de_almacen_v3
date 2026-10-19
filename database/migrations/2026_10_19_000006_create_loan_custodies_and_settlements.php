<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_unit_custodies', function (Blueprint $t) {
            $t->unsignedBigInteger('individual_unit_id')->primary();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('current_loan_item_id')->nullable()->unique();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
            $t->foreign(['individual_unit_id', 'product_id'], 'custody_identity_fk')->references(['id', 'product_id'])->on('individual_units')->restrictOnDelete();
            $t->foreign(['current_loan_item_id', 'individual_unit_id', 'product_id'], 'custody_item_fk')->references(['id', 'individual_unit_id', 'product_id'])->on('tool_loan_items')->restrictOnDelete();
            $t->foreign('current_loan_item_id', 'custody_movement_fk')->references('loan_item_id')->on('inventory_movements')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE individual_unit_custodies ADD CHECK (lock_version > 0)');
        Schema::create('loan_item_settlements', function (Blueprint $t) {
            $t->unsignedBigInteger('loan_item_id')->primary();
            $t->unsignedBigInteger('return_item_id')->unique();
            $t->unsignedBigInteger('individual_unit_id');
            $t->unsignedBigInteger('regularization_authorization_id')->nullable()->unique();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign(['return_item_id', 'loan_item_id', 'individual_unit_id'], 'settlement_membership_fk')->references(['id', 'loan_item_id', 'individual_unit_id'])->on('tool_return_items')->restrictOnDelete();
            $t->foreign('return_item_id', 'settlement_movement_fk')->references('return_item_id')->on('inventory_movements')->restrictOnDelete();
            $t->foreign(['regularization_authorization_id', 'return_item_id'], 'settlement_authorization_fk')->references(['id', 'return_item_id'])->on('return_regularization_authorizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Se conservan custodias y cierres históricos.');
    }
};
