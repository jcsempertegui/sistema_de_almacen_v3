<?php

namespace App\Services;

use App\Models\EjecucionRespaldo;
use App\Models\Usuario;
use App\Support\ValidacionOperacion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class RespaldosBaseDatos
{
    private function need(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['backup' => $message]);
        }
    }

    private function ident(string $nombre): string
    {
        return '`'.str_replace('`', '``', $nombre).'`';
    }

    private function root(): string
    {
        $path = config('sga.backup_directory');
        $this->need(is_string($path) && $path !== '', 'Configura un destino privado.');
        if (! is_dir($path)) {
            $this->need(mkdir($path, 0700, true), 'No se pudo preparar el destino.');
        }$root = realpath($path);
        $base = realpath(base_path());
        $this->need($root !== false && $base !== false && ! str_starts_with(strtolower(str_replace('\\', '/', $root)).'/', strtolower(str_replace('\\', '/', $base)).'/'), 'El destino debe estar fuera del repositorio.');
        if (PHP_OS_FAMILY === 'Windows') {
            $who = new Process(['whoami']);
            $who->mustRun();
            $usuario = trim($who->getOutput());
            $acl = new Process(['icacls', $root, '/inheritance:r', '/grant:r', $usuario.':(OI)(CI)F', '*S-1-5-18:(OI)(CI)F']);
            $acl->mustRun();
        } else {
            chmod($root, 0700);
        }

        return $root;
    }

    private function permiso(Usuario $u, string $codigo): void
    {
        app(ModificacionSegura::class)->exigirPermisos($u, $codigo);
    }

    public function request(Usuario $actor): EjecucionRespaldo
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $u) {
            $this->permiso($u, 'backups.generate');
            $id = DB::table('ejecuciones_respaldo')->insertGetId(['uuid' => (string) Str::uuid(), 'solicitado_por' => $u->id, 'fecha_creacion' => now(), 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'backup.requested', 'ejecucion_respaldo_id', $id);

            return EjecucionRespaldo::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }

    public function snapshot($connection): array
    {
        $resultado = [];
        foreach ($connection->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            $ddl = array_values((array) $connection->selectOne('SHOW CREATE TABLE '.$this->ident($table)))[1];
            $rows = $connection->table($table)->get()->map(fn ($r) => json_encode($r, JSON_THROW_ON_ERROR))->all();
            sort($rows);
            $resultado[$table] = ['count' => count($rows), 'data_sha256' => hash('sha256', implode("\n", $rows)), 'schema_sha256' => hash('sha256', preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl))];
        }ksort($resultado);

        return $resultado;
    }

    private function options($connection, string $file): void
    {
        $text = "[client]\nprotocol=tcp\n";
        foreach (['host' => 'host', 'port' => 'port', 'user' => 'username', 'password' => 'password'] as $name => $key) {
            $v = (string) $connection->getConfig($key);
            $this->need(! str_contains($v, "\0"), 'Credencial inválida.');
            $text .= $name.'="'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $v)."\"\n";
        }$handle = fopen($file, 'x');
        $this->need($handle !== false, 'El archivo de opciones ya existe.');
        fwrite($handle, $text);
        fclose($handle);
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($file, 0600);
        }
    }

    public function validateSql(string $path, array $expected): void
    {
        $text = file_get_contents($path);
        $this->need(strlen($text) > 0 && preg_match('/-- Dump completed on [^\r\n]+\s*\z/', $text) === 1, 'El respaldo está incompleto.');
        $this->need(preg_match('/^\s*(?:DROP\s+(?:TABLE|DATABASE)|CREATE\s+DATABASE|USE\s)/mi', $text) === 0, 'El SQL intenta cambiar el destino o borrar datos.');
        $this->need(preg_match('/`sga_rio_grande(?:_testing)?`\s*\./i', $text) === 0, 'El SQL contiene referencias al esquema de origen.');
        preg_match_all('/^CREATE TABLE `([^`]+)` /m', $text, $tables);
        $actual = $tables[1];
        sort($actual);
        $names = array_keys($expected);
        sort($names);
        $this->need($actual === $names, 'La lista de tablas del SQL no coincide.');
        preg_match_all('/^INSERT INTO `([^`]+)` /m', $text, $inserted);
        $counts = array_count_values($inserted[1]);
        foreach ($expected as $table => $meta) {
            $this->need(($counts[$table] ?? 0) === $meta['count'], 'Las filas del SQL no coinciden: '.$table);
        }
    }

    public function generate(Usuario $actor, EjecucionRespaldo $target): EjecucionRespaldo
    {
        $this->need(PHP_SAPI === 'cli', 'La generación se ejecuta desde consola controlada.');
        $owned = false;
        $client = null;
        $started = false;
        try {
            app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target, &$started) {
                $this->permiso($u, 'backups.generate');
                $run = EjecucionRespaldo::whereKey($target->id)->lockForUpdate()->firstOrFail();
                $this->need($run->estado === 'requested', 'El respaldo ya fue procesado.');
                DB::table('ejecuciones_respaldo')->where('id', $run->id)->update(['estado' => 'running', 'fecha_inicio' => now(), 'fecha_actualizacion' => now()]);
                app(AuditoriaCritica::class)->record($u, 'backup.started', 'ejecucion_respaldo_id', $run->id);
                $started = true;
            }, protectAdministrativeAccess: false);
            $root = $this->root();
            if (! app()->environment('testing')) {
                $this->need(! app()->isDownForMaintenance(), 'Ya existe mantenimiento; no se tomará su control.');
                $this->need(Artisan::call('down', ['--retry' => 60]) === 0, 'No se pudo activar mantenimiento.');
                $owned = true;
            }

            return app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target, $root, &$client, &$started) {
                $this->permiso($u, 'backups.generate');
                $run = EjecucionRespaldo::whereKey($target->id)->lockForUpdate()->firstOrFail();
                $this->need($run->estado === 'running', 'El respaldo ya fue procesado.');
                $connection = DB::connection();
                $database = $connection->selectOne('SELECT DATABASE() AS nombre')->nombre;
                $this->need(in_array($database, ['sga_rio_grande', 'sga_rio_grande_testing'], true), 'Base de origen no autorizada.');
                $this->need(! $connection->selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND ENGINE<>'InnoDB'")->n, 'Solo se admite snapshot InnoDB.');
                $objects = $connection->selectOne('SELECT (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE())+(SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE())+(SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE())+(SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()) AS n');
                $this->need((int) $objects->n === 0, 'Los objetos almacenados requieren revisión del procedimiento antes de respaldar.');
                // No database writes between snapshot and dump; the mutex excludes app writers.
                $integrity = app(IntegridadBaseDatos::class)->assertValid($connection);
                $before = $this->snapshot($connection);
                $file = $run->uuid.'.sql';
                $path = $root.'/'.$file;
                $this->need(! file_exists($path), 'Nunca sobrescribir un respaldo.');
                $client = $root.'/'.$run->uuid.'.cnf';
                $this->options($connection, $client);
                app(ClienteRespaldoSql::class)->dump($client, $path, $database, $root.'/'.$run->uuid.'.dump.log');
                $this->validateSql($path, $before);
                $this->need($before === $this->snapshot($connection), 'La base cambió durante el respaldo.');
                $manifiesto = ['database' => $database, 'php' => PHP_VERSION, 'laravel' => app()->version(), 'mariadb' => $connection->selectOne('SELECT VERSION() AS version')->version, 'captured_at' => now()->toIso8601String(), 'tables' => $before, 'integrity' => $integrity, 'footer_complete' => true, 'restore_verified' => false];
                DB::table('ejecuciones_respaldo')->where('id', $run->id)->update(['estado' => 'generated', 'nombre_archivo' => $file, 'tamano_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path), 'manifiesto' => json_encode($manifiesto, JSON_THROW_ON_ERROR), 'fecha_inicio' => now(), 'fecha_finalizacion' => now(), 'fecha_actualizacion' => now()]);
                file_put_contents($root.'/'.$run->uuid.'.manifiesto.json', json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                app(AuditoriaCritica::class)->record($u, 'backup.generated', 'ejecucion_respaldo_id', $run->id, ['sha256' => hash_file('sha256', $path), 'tamano_bytes' => filesize($path)]);

                return $run->fresh();
            }, protectAdministrativeAccess: false);
        } catch (\Throwable $e) {
            if ($started && EjecucionRespaldo::whereKey($target->id)->where('estado', 'running')->exists()) {
                DB::table('ejecuciones_respaldo')->where('id', $target->id)->update(['estado' => 'failed', 'fallo' => 'Falló la operación. Revisa el registro privado y el destino conservado.', 'fecha_finalizacion' => now(), 'fecha_actualizacion' => now()]);
                app(AuditoriaCritica::class)->record($actor, 'backup.failed', 'ejecucion_respaldo_id', $target->id);
            }throw $e;
        } finally {
            if ($client !== null && is_file($client)) {
                unlink($client);
            }if ($owned) {
                $this->need(Artisan::call('up') === 0 && ! app()->isDownForMaintenance(), 'No se pudo retirar el mantenimiento; requiere intervención administrativa.');
            }
        }
    }

    private function file(EjecucionRespaldo $run): string
    {
        $this->need((bool) preg_match('/^[a-f0-9-]{36}\.sql$/D', (string) $run->nombre_archivo), 'Nombre de respaldo inválido.');
        $root = $this->root();
        $path = realpath($root.'/'.$run->nombre_archivo);
        $this->need($path !== false && dirname($path) === $root, 'Archivo fuera del destino privado o inexistente.');

        return $path;
    }

    private function checkFile(EjecucionRespaldo $run): string
    {
        $this->need(in_array($run->estado, ['generated', 'verified']), 'El respaldo todavía no está disponible.');
        $path = $this->file($run);
        $this->need(filesize($path) === $run->tamano_bytes && hash_equals($run->sha256, hash_file('sha256', $path)), 'El tamaño o SHA-256 cambió.');
        $this->validateSql($path, $run->manifiesto['tables']);

        return $path;
    }

    public function verify(Usuario $actor, EjecucionRespaldo $target): EjecucionRespaldo
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target) {
            $this->permiso($u, 'backups.verify');
            $run = EjecucionRespaldo::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->checkFile($run);
            DB::table('ejecuciones_respaldo')->where('id', $run->id)->update(['estado' => 'verified', 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'backup.integrity_verified', 'ejecucion_respaldo_id', $run->id, ['restoration_verified' => false]);

            return $run->fresh();
        }, protectAdministrativeAccess: false);
    }

    public function download(Usuario $actor, EjecucionRespaldo $target): string
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target) {
            $this->permiso($u, 'backups.download');
            $run = EjecucionRespaldo::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $path = $this->checkFile($run);
            app(AuditoriaCritica::class)->record($u, 'backup.download_authorized', 'ejecucion_respaldo_id', $run->id);

            return $path;
        }, protectAdministrativeAccess: false);
    }

    public function requestRehearsal(Usuario $actor, EjecucionRespaldo $target, string $motivo): int
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target, $motivo) {
            $this->permiso($u, 'backup_rehearsals.request');
            ValidacionOperacion::make(['motivo' => $motivo], ['motivo' => 'required|string|max:1000'])->validate();
            $run = EjecucionRespaldo::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->checkFile($run);
            $id = DB::table('ensayos_restauracion_respaldo')->insertGetId(['ejecucion_respaldo_id' => $run->id, 'base_destino' => 'sga_rio_grande_rehearsal_'.bin2hex(random_bytes(8)), 'solicitado_por' => $u->id, 'motivo' => trim($motivo), 'fecha_creacion' => now(), 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'rehearsal.requested', 'ensayo_id', $id);

            return $id;
        }, protectAdministrativeAccess: false);
    }

    public function approveRehearsal(Usuario $actor, int $id): void
    {
        app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($id) {
            $this->permiso($u, 'backup_rehearsals.authorize');
            $r = DB::table('ensayos_restauracion_respaldo')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->need($r->estado === 'requested' && $r->solicitado_por !== $u->id, 'Se requiere una solicitud pendiente de otra persona.');
            DB::table('ensayos_restauracion_respaldo')->where('id', $id)->update(['estado' => 'approved', 'autorizado_por' => $u->id, 'fecha_autorizacion' => now(), 'fecha_vencimiento' => now()->addHours(24), 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'rehearsal.approved', 'ensayo_id', $id, ['access_proof' => app(AccesoAutorizacion::class)->proof($u)]);
        }, protectAdministrativeAccess: false);
    }

    public function restoreRehearsal(Usuario $actor, int $id, string $confirmation): void
    {
        $this->need(PHP_SAPI === 'cli' && ! app()->environment('testing'), 'Los ensayos reales requieren consola operativa y autorización específica; las pruebas no crean otro esquema.');
        // Isolated DDL commits implicitly: do not wrap it in the application transaction.
        $r = app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($id, $confirmation) {
            $this->permiso($u, 'backup_rehearsals.execute');
            $r = DB::table('ensayos_restauracion_respaldo')->where('id', $id)->lockForUpdate()->firstOrFail();
            $event = DB::table('eventos_operacion_critica')->where('ensayo_id', $id)->where('accion', 'rehearsal.approved')->orderByDesc('id')->lockForUpdate()->first();
            $proof = $event ? json_decode($event->detalle, true, flags: JSON_THROW_ON_ERROR) : [];
            $this->need($r->estado === 'approved' && $r->fecha_vencimiento > now()->toDateTimeString() && app(AccesoAutorizacion::class)->valid((int) $r->autorizado_por, 'backup_rehearsals.authorize', $proof['access_proof'] ?? []), 'Autorización inexistente, revocada o caducada.');
            $this->need($r->base_destino === $confirmation && preg_match('/^sga_rio_grande_rehearsal_[a-f0-9]{16}$/D', $confirmation) === 1, 'Confirmación de destino aislado incorrecta.');
            DB::table('ensayos_restauracion_respaldo')->where('id', $id)->update(['estado' => 'running', 'ejecutado_por' => $u->id, 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'rehearsal.started', 'ensayo_id', $id);

            return $r;
        }, protectAdministrativeAccess: false);
        $root = $this->root();
        $client = $root.'/rehearsal-'.$id.'.cnf';
        $db = DB::connection();
        $run = EjecucionRespaldo::findOrFail($r->ejecucion_respaldo_id);
        try {
            $path = $this->checkFile($run);
            $this->need($db->table('information_schema.SCHEMATA')->where('SCHEMA_NAME', $confirmation)->count() === 0, 'El destino ya existe; no se sobrescribirá.');
            $this->options($db, $client);
            $db->statement('CREATE DATABASE '.$this->ident($confirmation).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            app(ClienteRespaldoSql::class)->restore($client, $path, $confirmation, $root.'/rehearsal-'.$id.'.log');
            config(['database.connections.sga_rehearsal' => array_replace($db->getConfig(), ['nombre' => 'sga_rehearsal', 'database' => $confirmation, 'url' => null])]);
            DB::purge('sga_rehearsal');
            $restored = DB::connection('sga_rehearsal');
            $this->need($restored->selectOne('SELECT DATABASE() AS nombre')->name === $confirmation, 'Destino incorrecto.');
            $this->need($this->snapshot($restored) === $run->manifiesto['tables'], 'Los datos o estructuras restaurados no coinciden.');
            $integrity = app(IntegridadBaseDatos::class)->assertValid($restored);
            DB::table('ensayos_restauracion_respaldo')->where('id', $id)->update(['estado' => 'verified', 'resultado' => json_encode(['tables_match' => true, 'data_match' => true, 'integrity' => $integrity, 'database_retained' => true]), 'fecha_finalizacion' => now(), 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($actor, 'rehearsal.verified', 'ensayo_id', $id, ['database_retained' => true]);
        } catch (\Throwable $e) {
            DB::table('ensayos_restauracion_respaldo')->where('id', $id)->update(['estado' => 'failed', 'resultado' => json_encode(['message' => 'Ensayo fallido; conservar destino y registros privados.']), 'fecha_finalizacion' => now(), 'fecha_actualizacion' => now()]);
            throw $e;
        } finally {
            if (is_file($client)) {
                unlink($client);
            }
        }
    }
}
