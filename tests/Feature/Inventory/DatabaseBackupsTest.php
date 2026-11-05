<?php

namespace Tests\Feature\Inventory;

use App\Models\Usuario;
use App\Services\ClienteRespaldoSql;
use App\Services\RespaldosBaseDatos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeSqlBackupClient;
use Tests\TestCase;

class DatabaseBackupsTest extends TestCase
{
    private function setupBackup(): array
    {
        $root = sys_get_temp_dir().'/sga-sprint5-'.bin2hex(random_bytes(8));
        config(['sga.backup_directory' => $root]);
        $fake = new FakeSqlBackupClient;
        $this->app->instance(ClienteRespaldoSql::class, $fake);
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $service = app(RespaldosBaseDatos::class);
        $run = $service->request($actor);

        return compact('root', 'fake', 'actor', 'service', 'run');
    }

    public function test_private_generation_integrity_download_and_rehearsal_approval_are_distinct(): void
    {
        $c = $this->setupBackup();
        $run = $c['service']->generate($c['actor'], $c['run']);
        $this->assertSame('generated', $run->estado);
        $this->assertFalse($run->manifiesto['restore_verified']);
        $this->assertSame([], glob($c['root'].'/*.cnf'));
        $run = $c['service']->verify($c['actor'], $run);
        $this->assertSame('verified', $run->estado);
        $path = $c['service']->download($c['actor'], $run);
        $this->assertFileExists($path);
        $this->assertSame($run->sha256, hash_file('sha256', $path));
        $id = $c['service']->requestRehearsal($c['actor'], $run, 'Comprobar recuperación aislada');
        try {
            $c['service']->approveRehearsal($c['actor'], $id);
            $this->fail('Self authorization accepted');
        } catch (ValidationException) {
            $this->assertDatabaseHas('ensayos_restauracion_respaldo', ['id' => $id, 'estado' => 'requested']);
        }
        $other = Usuario::factory()->create(['rol_id' => 1]);
        $c['service']->approveRehearsal($other, $id);
        $this->assertDatabaseHas('ensayos_restauracion_respaldo', ['id' => $id, 'estado' => 'approved']);
        $target = DB::table('ensayos_restauracion_respaldo')->where('id', $id)->value('base_destino');
        try {
            $c['service']->restoreRehearsal($other, $id, $target);
            $this->fail('A PHPUnit test created another schema');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('information_schema.SCHEMATA')->where('SCHEMA_NAME', $target)->count());
        }
        $this->signIn($c['actor'])->get(route('backups.index'))->assertOk()->assertSee('Verificado');
        $this->get(route('backups.manifiesto', $run))->assertOk();
    }

    public function test_tampering_and_failure_do_not_produce_a_verified_backup_or_keep_credentials(): void
    {
        $c = $this->setupBackup();
        $run = $c['service']->generate($c['actor'], $c['run']);
        $path = $c['root'].'/'.$run->nombre_archivo;
        file_put_contents($path, "tampered\n", FILE_APPEND);
        try {
            $c['service']->verify($c['actor'], $run);
            $this->fail('Tampered backup accepted');
        } catch (ValidationException) {
            $this->assertSame('generated', $run->fresh()->estado);
        }
        $other = $c['service']->request($c['actor']);
        $c['fake']->fail = true;
        try {
            $c['service']->generate($c['actor'], $other);
            $this->fail('Failed dump accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected dump fallo', $e->getMessage());
        }
        $this->assertSame('failed', $other->fresh()->estado);
        $this->assertSame([], glob($c['root'].'/*.cnf'));
    }

    public function test_download_and_generation_reject_a_read_only_profile(): void
    {
        $c = $this->setupBackup();
        $auditor = Usuario::factory()->create(['rol_id' => 3]);
        $this->signIn($auditor)->get(route('backups.download', $c['run']))->assertForbidden();
        $this->get(route('backups.manifiesto', $c['run']))->assertForbidden();
        try {
            $c['service']->generate($auditor, $c['run']);
            $this->fail('Unauthorized generator');
        } catch (AuthorizationException) {
            $this->assertSame('requested', $c['run']->fresh()->estado);
            $this->assertDirectoryDoesNotExist($c['root']);
        }
    }
}
