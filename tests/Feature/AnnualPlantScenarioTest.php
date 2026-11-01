<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AnnualPlantScenario;
use App\Services\DashboardOperations;
use App\Support\TestingDatabaseGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AnnualPlantScenarioTest extends TestCase
{
    public function test_full_year_preserves_admin_permissions_and_reconciles_stock_and_custody(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $passwordHash = $admin->password;
        $permissions = DB::table('permission_role')->orderBy('id')->get()->toJson();
        $end = CarbonImmutable::parse('2026-10-31', 'America/La_Paz');
        $identity = TestingDatabaseGuard::identity(DB::connection()->getPdo());
        $result = app(AnnualPlantScenario::class)->run($admin, $end, $identity);
        $this->assertSame('2025-11-01', $result['from']);
        $this->assertSame(24, $result['summary']['workers']);
        $this->assertSame(5, $result['summary']['users']);
        $this->assertSame(31, $result['summary']['products']);
        $this->assertSame(64, $result['summary']['individual_units']);
        $this->assertSame(0, $result['summary']['stock_mismatches']);
        $this->assertSame(0, $result['summary']['custody_presence_conflicts']);
        $this->assertSame($passwordHash, $admin->fresh()->password);
        $this->assertSame($permissions, DB::table('permission_role')->orderBy('id')->get()->toJson());
        $this->assertSame(20, DB::table('workers')->where('area', 'Operaciones y mantenimiento')->count());
        $this->assertSame(4, DB::table('workers')->where('area', 'Staff del contrato')->count());
        $this->assertSame(48, DB::table('material_deliveries')->where('status', 'confirmed')->where('notes', 'like', '%Dotación semestral%')->count());
        $this->assertSame(365, DB::table('material_deliveries')->where('status', 'confirmed')->where('notes', 'like', '%Solicitud diaria%')->distinct()->count('document_date'));
        $this->assertSame(1, DB::table('material_deliveries')->where('status', 'draft')->count());
        $this->assertSame(4, count($result['credentials']));
        $this->assertGreaterThan(1000, $result['summary']['inventory_movements']);
        $this->assertSame('2025-11-01', substr(DB::table('inventory_movements')->min('posted_at'), 0, 10));
        $this->assertSame('2026-10-31', substr(DB::table('inventory_movements')->max('posted_at'), 0, 10));
        $this->signIn($admin)->get(route('dashboard'))->assertOk()->assertSee('Últimos movimientos');
        $this->travelTo($end->setTime(20, 0));
        $dashboard = app(DashboardOperations::class)->forUser($admin);
        $this->assertSame(40, $dashboard['loans']['pending']);
        $this->assertSame(8, $dashboard['loans']['overdue']);
        $before = DB::table('inventory_movements')->count();
        try {
            app(AnnualPlantScenario::class)->run($admin, $end, $identity);
            $this->fail('Se permitió repetir la simulación.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('vacíos', $e->getMessage());
        }
        $this->assertSame($before, DB::table('inventory_movements')->count());
    }

    public function test_wrong_identity_is_rejected_before_any_insert(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $identity = TestingDatabaseGuard::identity(DB::connection()->getPdo());
        $identity['database_name'] = 'sga_rio_grande';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Destino no autorizado');
        app(AnnualPlantScenario::class)->run($admin, CarbonImmutable::parse('2026-10-31', 'America/La_Paz'), $identity);
    }

    public function test_failure_during_generation_rolls_back_all_scenario_records_and_restores_clock(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $before = now()->toDateString();
        try {
            app(AnnualPlantScenario::class)->run($admin, CarbonImmutable::parse('2026-10-31', 'America/La_Paz'), TestingDatabaseGuard::identity(DB::connection()->getPdo()), function () {
                throw new RuntimeException('Fallo simulado después del primer mes');
            });
            $this->fail('No se provocó el fallo esperado.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Fallo simulado', $e->getMessage());
        }
        foreach (['workers', 'products', 'inventory_movements', 'tool_loans', 'material_deliveries'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($before, now()->toDateString());
    }
}
