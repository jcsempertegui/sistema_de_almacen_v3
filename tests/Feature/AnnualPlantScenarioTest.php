<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\EscenarioAnualPlanta;
use App\Services\OperacionesPanel;
use App\Support\TestingDatabaseGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AnnualPlantScenarioTest extends TestCase
{
    public function test_full_year_preserves_admin_permissions_and_reconciles_stock_and_custody(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $passwordHash = $admin->contrasena;
        $permisos = DB::table('permiso_rol')->orderBy('id')->get()->toJson();
        $end = CarbonImmutable::parse('2026-10-31', 'America/La_Paz');
        $identity = TestingDatabaseGuard::identity(DB::connection()->getPdo());
        $resultado = app(EscenarioAnualPlanta::class)->run($admin, $end, $identity);
        $this->assertSame('2025-11-01', $resultado['from']);
        $this->assertSame(24, $resultado['summary']['trabajadores']);
        $this->assertSame(5, $resultado['summary']['usuarios']);
        $this->assertSame(116, $resultado['summary']['productos']);
        $this->assertSame(192, $resultado['summary']['unidades_individuales']);
        $this->assertDatabaseHas('productos', ['codigo_interno' => 'RG-TORQUIMETRO', 'modo_control' => 'individual', 'prestable' => 1]);
        $this->assertDatabaseHas('productos', ['codigo_interno' => 'RG-OVEROL-XXXL']);
        $this->assertDatabaseHas('productos', ['codigo_interno' => 'RG-BOTIN-46']);
        $this->assertSame(0, $resultado['summary']['stock_mismatches']);
        $this->assertSame(0, $resultado['summary']['custody_presence_conflicts']);
        $this->assertSame($passwordHash, $admin->fresh()->contrasena);
        $this->assertSame($permisos, DB::table('permiso_rol')->orderBy('id')->get()->toJson());
        $this->assertSame(20, DB::table('trabajadores')->where('area', 'Operaciones y mantenimiento')->count());
        $this->assertSame(4, DB::table('trabajadores')->where('area', 'Staff del contrato')->count());
        $this->assertSame(48, DB::table('entregas_materiales')->where('estado', 'confirmed')->where('observaciones', 'like', '%Dotación semestral%')->count());
        $this->assertSame(365, DB::table('entregas_materiales')->where('estado', 'confirmed')->where('observaciones', 'like', '%Solicitud diaria%')->distinct()->count('fecha_documento'));
        $this->assertSame(1, DB::table('entregas_materiales')->where('estado', 'draft')->count());
        $this->assertSame(4, count($resultado['credentials']));
        $this->assertGreaterThan(1000, $resultado['summary']['movimientos_inventario']);
        $this->assertSame('2025-11-01', substr(DB::table('movimientos_inventario')->min('fecha_registro'), 0, 10));
        $this->assertSame('2026-10-31', substr(DB::table('movimientos_inventario')->max('fecha_registro'), 0, 10));
        $this->signIn($admin)->get(route('dashboard'))->assertOk()->assertSee('Últimos movimientos');
        $this->travelTo($end->setTime(20, 0));
        $dashboard = app(OperacionesPanel::class)->paraUsuario($admin);
        $this->assertSame(40, $dashboard['loans']['pending']);
        $this->assertSame(8, $dashboard['loans']['overdue']);
        $before = DB::table('movimientos_inventario')->count();
        try {
            app(EscenarioAnualPlanta::class)->run($admin, $end, $identity);
            $this->fail('Se permitió repetir la simulación.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('vacíos', $e->getMessage());
        }
        $this->assertSame($before, DB::table('movimientos_inventario')->count());
    }

    public function test_wrong_identity_is_rejected_before_any_insert(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $identity = TestingDatabaseGuard::identity(DB::connection()->getPdo());
        $identity['database_name'] = 'sga_rio_grande';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Destino no autorizado');
        app(EscenarioAnualPlanta::class)->run($admin, CarbonImmutable::parse('2026-10-31', 'America/La_Paz'), $identity);
    }

    public function test_failure_during_generation_rolls_back_all_scenario_records_and_restores_clock(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $before = now()->toDateString();
        try {
            app(EscenarioAnualPlanta::class)->run($admin, CarbonImmutable::parse('2026-10-31', 'America/La_Paz'), TestingDatabaseGuard::identity(DB::connection()->getPdo()), function () {
                throw new RuntimeException('Fallo simulado después del primer mes');
            });
            $this->fail('No se provocó el fallo esperado.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Fallo simulado', $e->getMessage());
        }
        foreach (['trabajadores', 'productos', 'movimientos_inventario', 'prestamos_herramientas', 'entregas_materiales'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('usuarios', 1);
        $this->assertSame($before, now()->toDateString());
    }
}
