<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\AuditoriaAdministrativa;
use App\Services\RenovarDatosSimulados;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RenewalAuditTest extends TestCase
{
    public function test_renewal_audit_can_be_saved_in_the_existing_schema_without_exposing_secrets(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        app(AuditoriaAdministrativa::class)->record(
            $admin, 'datos_simulados.renovados', $admin,
            ['registros_retirados' => ['productos' => 31, 'movimientos_inventario' => 1931]],
            ['periodo_desde' => '2025-11-03', 'periodo_hasta' => '2026-11-02', 'resumen' => ['productos' => 116]],
            RenovarDatosSimulados::ORIGEN_AUDITORIA,
        );
        $registro = DB::table('registros_auditoria_administrativa')->where('accion', 'datos_simulados.renovados')->first();
        $this->assertNotNull($registro);
        $this->assertSame($admin->id, $registro->actor_id);
        $this->assertSame('console', $registro->origen);
        $this->assertStringContainsString('116', $registro->datos_posteriores);
        $this->assertStringNotContainsString($admin->contrasena, $registro->datos_posteriores);
    }
}
