<?php

use App\Support\EsquemaEspanol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        EsquemaEspanol::aplicar(DB::connection());
    }

    public function down(): void
    {
        throw new RuntimeException('La traducción conserva historial y requiere recuperación administrativa verificada. No se revierte automáticamente.');
    }
};
