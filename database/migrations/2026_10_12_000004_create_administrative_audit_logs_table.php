<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name', 255)->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->string('origin', 16);
            $table->string('ip_address', 45)->nullable();
            $table->text('before_data')->nullable();
            $table->text('after_data');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'id']);
            $table->index(['action', 'created_at']);
        });
        DB::statement('ALTER TABLE administrative_audit_logs ADD CONSTRAINT aal_one_subject CHECK ((role_id IS NOT NULL) + (user_id IS NOT NULL) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_audit_logs');
    }
};
