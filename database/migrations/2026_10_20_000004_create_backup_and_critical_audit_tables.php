<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('status', 12)->default('requested');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->string('file_name')->nullable()->unique();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->char('sha256', 64)->nullable();
            $t->longText('manifest')->nullable();
            $t->text('failure')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE backup_runs ADD CHECK (status IN ('requested','running','generated','verified','failed')), ADD CHECK (status NOT IN ('generated','verified') OR (file_name IS NOT NULL AND size_bytes>0 AND sha256 IS NOT NULL AND manifest IS NOT NULL AND completed_at IS NOT NULL))");
        Schema::create('backup_restore_rehearsals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('backup_run_id')->constrained()->restrictOnDelete();
            $t->string('status', 12)->default('requested');
            $t->string('target_database', 64)->unique();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('reason', 1000);
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->longText('result')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE backup_restore_rehearsals ADD CHECK (status IN ('requested','approved','running','verified','failed','revoked')), ADD CHECK (CHAR_LENGTH(TRIM(reason))>0), ADD CHECK (approved_by IS NULL OR approved_by<>requested_by), ADD CHECK (status NOT IN ('approved','running','verified') OR (approved_by IS NOT NULL AND approved_at IS NOT NULL AND expires_at IS NOT NULL)), ADD CHECK (target_database REGEXP '^sga_rio_grande_rehearsal_[a-f0-9]{16}$')");
        Schema::create('critical_operation_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->string('action', 80);
            $t->foreignId('correction_id')->nullable()->constrained('inventory_corrections')->restrictOnDelete();
            $t->foreignId('backup_run_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('rehearsal_id')->nullable()->constrained('backup_restore_rehearsals')->restrictOnDelete();
            $t->longText('payload');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['action', 'created_at']);
        });
        DB::statement('ALTER TABLE critical_operation_events ADD CHECK ((correction_id IS NOT NULL)+(backup_run_id IS NOT NULL)+(rehearsal_id IS NOT NULL)=1)');
    }

    public function down(): void
    {
        throw new RuntimeException('Los respaldos y la auditoría requieren recuperación controlada.');
    }
};
