<?php

namespace Tests\Feature;

use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\RenovarDatosSimulados;
use App\Support\OperacionLocalAutorizada;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AuthorizedRenewalTest extends TestCase
{
    public function test_invalid_backup_never_grants_capability_or_executes_callback(): void
    {
        $executed = false;
        try {
            OperacionLocalAutorizada::ejecutar(DB::connection(), [], 'archivo-inexistente.sql', str_repeat('0', 64), function () use (&$executed) {
                $executed = true;
            });
            $this->fail('Se aceptó un respaldo inexistente.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('respaldo externo', $e->getMessage());
        }
        $this->assertFalse($executed);
        $this->assertFalse(OperacionLocalAutorizada::vigente(DB::connection()));
    }

    public function test_tampered_backup_is_rejected_before_opening_a_deployment_scope(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sga-respaldo-');
        file_put_contents($file, str_repeat('a', 1100));
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('respaldo externo');
            OperacionLocalAutorizada::ejecutar(DB::connection(), [], $file, hash('sha256', 'otro contenido'), fn () => $this->fail('No debe ejecutarse.'));
        } finally {
            unlink($file);
            $this->assertFalse(OperacionLocalAutorizada::vigente(DB::connection()));
        }
    }

    public function test_failure_after_replacing_records_restores_the_previous_records_and_access(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $worker = Trabajador::factory()->create(['nombres' => 'Registro anterior']);
        $oldUser = Usuario::factory()->create(['rol_id' => 3]);
        $oldPassword = $admin->contrasena;
        $links = DB::table('permiso_rol')->get()->toJson();
        try {
            app(RenovarDatosSimulados::class)->ejecutar($admin, CarbonImmutable::parse('2026-10-31', 'America/La_Paz'), function () {
                throw new RuntimeException('Interrupción después del primer mes nuevo');
            });
            $this->fail('Faltó la interrupción prevista.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Interrupción después', $e->getMessage());
        }
        $this->assertDatabaseHas('trabajadores', ['id' => $worker->id, 'nombres' => 'Registro anterior']);
        $this->assertDatabaseHas('usuarios', ['id' => $oldUser->id]);
        $this->assertDatabaseCount('trabajadores', 1);
        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame($oldPassword, $admin->fresh()->contrasena);
        $this->assertSame($links, DB::table('permiso_rol')->get()->toJson());
        $this->assertSame(1, (int) DB::selectOne('SELECT @@foreign_key_checks AS fk')->fk);
    }
}
