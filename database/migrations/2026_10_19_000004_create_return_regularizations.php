<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_regularization_authorizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_item_id')->constrained('tool_return_items')->restrictOnDelete();
            $t->string('status', 12)->default('requested');
            $t->string('reason', 1000);
            $t->char('fingerprint', 64);
            $t->text('reference_snapshot');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('requested_at');
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->unique(['id', 'return_item_id'], 'regularization_item_uq');
            $t->index(['return_item_id', 'status']);
        });
        DB::statement("ALTER TABLE return_regularization_authorizations ADD CHECK (status IN ('requested','approved','revoked','used')), ADD CHECK (CHAR_LENGTH(TRIM(reason)) > 0), ADD CHECK (status NOT IN ('approved','used') OR (approved_by IS NOT NULL AND approved_at IS NOT NULL AND expires_at IS NOT NULL)), ADD CHECK ((status = 'used' AND used_at IS NOT NULL) OR (status <> 'used' AND used_at IS NULL))");
        Schema::create('return_regularization_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('authorization_id')->constrained('return_regularization_authorizations')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 16);
            $t->text('payload');
            $t->timestamp('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE return_regularization_events ADD CHECK (action IN ('requested','approved','revoked','used'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva el historial de autorizaciones.');
    }
};
