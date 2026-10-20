<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\User;
use App\Support\OperationValidation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class DatabaseBackups
{
    private function need(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['backup' => $message]);
        }
    }

    private function ident(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
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
            $user = trim($who->getOutput());
            $acl = new Process(['icacls', $root, '/inheritance:r', '/grant:r', $user.':(OI)(CI)F', '*S-1-5-18:(OI)(CI)F']);
            $acl->mustRun();
        } else {
            chmod($root, 0700);
        }

        return $root;
    }

    private function permission(User $u, string $code): void
    {
        app(SecurityMutation::class)->requirePermissions($u, $code);
    }

    public function request(User $actor): BackupRun
    {
        return app(SecurityMutation::class)->run($actor, function (User $u) {
            $this->permission($u, 'backups.generate');
            $id = DB::table('backup_runs')->insertGetId(['uuid' => (string) Str::uuid(), 'requested_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'backup.requested', 'backup_run_id', $id);

            return BackupRun::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }

    public function snapshot($connection): array
    {
        $result = [];
        foreach ($connection->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            $ddl = array_values((array) $connection->selectOne('SHOW CREATE TABLE '.$this->ident($table)))[1];
            $rows = $connection->table($table)->get()->map(fn ($r) => json_encode($r, JSON_THROW_ON_ERROR))->all();
            sort($rows);
            $result[$table] = ['count' => count($rows), 'data_sha256' => hash('sha256', implode("\n", $rows)), 'schema_sha256' => hash('sha256', preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl))];
        }ksort($result);

        return $result;
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

    public function generate(User $actor, BackupRun $target): BackupRun
    {
        $this->need(PHP_SAPI === 'cli', 'La generación se ejecuta desde consola controlada.');
        $owned = false;
        $client = null;
        $started = false;
        try {
            app(SecurityMutation::class)->run($actor, function (User $u) use ($target, &$started) {
                $this->permission($u, 'backups.generate');
                $run = BackupRun::whereKey($target->id)->lockForUpdate()->firstOrFail();
                $this->need($run->status === 'requested', 'El respaldo ya fue procesado.');
                DB::table('backup_runs')->where('id', $run->id)->update(['status' => 'running', 'started_at' => now(), 'updated_at' => now()]);
                app(CriticalAudit::class)->record($u, 'backup.started', 'backup_run_id', $run->id);
                $started = true;
            }, protectAdministrativeAccess: false);
            $root = $this->root();
            if (! app()->environment('testing')) {
                $this->need(! app()->isDownForMaintenance(), 'Ya existe mantenimiento; no se tomará su control.');
                $this->need(Artisan::call('down', ['--retry' => 60]) === 0, 'No se pudo activar mantenimiento.');
                $owned = true;
            }

            return app(SecurityMutation::class)->run($actor, function (User $u) use ($target, $root, &$client, &$started) {
                $this->permission($u, 'backups.generate');
                $run = BackupRun::whereKey($target->id)->lockForUpdate()->firstOrFail();
                $this->need($run->status === 'running', 'El respaldo ya fue procesado.');
                $connection = DB::connection();
                $database = $connection->selectOne('SELECT DATABASE() AS name')->name;
                $this->need(in_array($database, ['sga_rio_grande', 'sga_rio_grande_testing'], true), 'Base de origen no autorizada.');
                $this->need(! $connection->selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND ENGINE<>'InnoDB'")->n, 'Solo se admite snapshot InnoDB.');
                $objects = $connection->selectOne('SELECT (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE())+(SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE())+(SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE())+(SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()) AS n');
                $this->need((int) $objects->n === 0, 'Los objetos almacenados requieren revisión del procedimiento antes de respaldar.');
                // No database writes between snapshot and dump; the mutex excludes app writers.
                $integrity = app(DatabaseIntegrity::class)->assertValid($connection);
                $before = $this->snapshot($connection);
                $file = $run->uuid.'.sql';
                $path = $root.'/'.$file;
                $this->need(! file_exists($path), 'Nunca sobrescribir un respaldo.');
                $client = $root.'/'.$run->uuid.'.cnf';
                $this->options($connection, $client);
                app(SqlBackupClient::class)->dump($client, $path, $database, $root.'/'.$run->uuid.'.dump.log');
                $this->validateSql($path, $before);
                $this->need($before === $this->snapshot($connection), 'La base cambió durante el respaldo.');
                $manifest = ['database' => $database, 'php' => PHP_VERSION, 'laravel' => app()->version(), 'mariadb' => $connection->selectOne('SELECT VERSION() AS version')->version, 'captured_at' => now()->toIso8601String(), 'tables' => $before, 'integrity' => $integrity, 'footer_complete' => true, 'restore_verified' => false];
                DB::table('backup_runs')->where('id', $run->id)->update(['status' => 'generated', 'file_name' => $file, 'size_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path), 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR), 'started_at' => now(), 'completed_at' => now(), 'updated_at' => now()]);
                file_put_contents($root.'/'.$run->uuid.'.manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                app(CriticalAudit::class)->record($u, 'backup.generated', 'backup_run_id', $run->id, ['sha256' => hash_file('sha256', $path), 'size_bytes' => filesize($path)]);

                return $run->fresh();
            }, protectAdministrativeAccess: false);
        } catch (\Throwable $e) {
            if ($started && BackupRun::whereKey($target->id)->where('status', 'running')->exists()) {
                DB::table('backup_runs')->where('id', $target->id)->update(['status' => 'failed', 'failure' => 'Falló la operación. Revisa el registro privado y el destino conservado.', 'completed_at' => now(), 'updated_at' => now()]);
                app(CriticalAudit::class)->record($actor, 'backup.failed', 'backup_run_id', $target->id);
            }throw $e;
        } finally {
            if ($client !== null && is_file($client)) {
                unlink($client);
            }if ($owned) {
                $this->need(Artisan::call('up') === 0 && ! app()->isDownForMaintenance(), 'No se pudo retirar el mantenimiento; requiere intervención administrativa.');
            }
        }
    }

    private function file(BackupRun $run): string
    {
        $this->need((bool) preg_match('/^[a-f0-9-]{36}\.sql$/D', (string) $run->file_name), 'Nombre de respaldo inválido.');
        $root = $this->root();
        $path = realpath($root.'/'.$run->file_name);
        $this->need($path !== false && dirname($path) === $root, 'Archivo fuera del destino privado o inexistente.');

        return $path;
    }

    private function checkFile(BackupRun $run): string
    {
        $this->need(in_array($run->status, ['generated', 'verified']), 'El respaldo todavía no está disponible.');
        $path = $this->file($run);
        $this->need(filesize($path) === $run->size_bytes && hash_equals($run->sha256, hash_file('sha256', $path)), 'El tamaño o SHA-256 cambió.');
        $this->validateSql($path, $run->manifest['tables']);

        return $path;
    }

    public function verify(User $actor, BackupRun $target): BackupRun
    {
        return app(SecurityMutation::class)->run($actor, function (User $u) use ($target) {
            $this->permission($u, 'backups.verify');
            $run = BackupRun::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->checkFile($run);
            DB::table('backup_runs')->where('id', $run->id)->update(['status' => 'verified', 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'backup.integrity_verified', 'backup_run_id', $run->id, ['restoration_verified' => false]);

            return $run->fresh();
        }, protectAdministrativeAccess: false);
    }

    public function download(User $actor, BackupRun $target): string
    {
        return app(SecurityMutation::class)->run($actor, function (User $u) use ($target) {
            $this->permission($u, 'backups.download');
            $run = BackupRun::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $path = $this->checkFile($run);
            app(CriticalAudit::class)->record($u, 'backup.download_authorized', 'backup_run_id', $run->id);

            return $path;
        }, protectAdministrativeAccess: false);
    }

    public function requestRehearsal(User $actor, BackupRun $target, string $reason): int
    {
        return app(SecurityMutation::class)->run($actor, function (User $u) use ($target, $reason) {
            $this->permission($u, 'backup_rehearsals.request');
            OperationValidation::make(['reason' => $reason], ['reason' => 'required|string|max:1000'])->validate();
            $run = BackupRun::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->checkFile($run);
            $id = DB::table('backup_restore_rehearsals')->insertGetId(['backup_run_id' => $run->id, 'target_database' => 'sga_rio_grande_rehearsal_'.bin2hex(random_bytes(8)), 'requested_by' => $u->id, 'reason' => trim($reason), 'created_at' => now(), 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'rehearsal.requested', 'rehearsal_id', $id);

            return $id;
        }, protectAdministrativeAccess: false);
    }

    public function approveRehearsal(User $actor, int $id): void
    {
        app(SecurityMutation::class)->run($actor, function (User $u) use ($id) {
            $this->permission($u, 'backup_rehearsals.authorize');
            $r = DB::table('backup_restore_rehearsals')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->need($r->status === 'requested' && $r->requested_by !== $u->id, 'Se requiere una solicitud pendiente de otra persona.');
            DB::table('backup_restore_rehearsals')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $u->id, 'approved_at' => now(), 'expires_at' => now()->addHours(24), 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'rehearsal.approved', 'rehearsal_id', $id, ['access_proof' => app(ApprovalAccess::class)->proof($u)]);
        }, protectAdministrativeAccess: false);
    }

    public function restoreRehearsal(User $actor, int $id, string $confirmation): void
    {
        $this->need(PHP_SAPI === 'cli' && ! app()->environment('testing'), 'Los ensayos reales requieren consola operativa y autorización específica; las pruebas no crean otro esquema.');
        // Isolated DDL commits implicitly: do not wrap it in the application transaction.
        $r = app(SecurityMutation::class)->run($actor, function (User $u) use ($id, $confirmation) {
            $this->permission($u, 'backup_rehearsals.execute');
            $r = DB::table('backup_restore_rehearsals')->where('id', $id)->lockForUpdate()->firstOrFail();
            $event = DB::table('critical_operation_events')->where('rehearsal_id', $id)->where('action', 'rehearsal.approved')->orderByDesc('id')->lockForUpdate()->first();
            $proof = $event ? json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR) : [];
            $this->need($r->status === 'approved' && $r->expires_at > now()->toDateTimeString() && app(ApprovalAccess::class)->valid((int) $r->approved_by, 'backup_rehearsals.authorize', $proof['access_proof'] ?? []), 'Autorización inexistente, revocada o caducada.');
            $this->need($r->target_database === $confirmation && preg_match('/^sga_rio_grande_rehearsal_[a-f0-9]{16}$/D', $confirmation) === 1, 'Confirmación de destino aislado incorrecta.');
            DB::table('backup_restore_rehearsals')->where('id', $id)->update(['status' => 'running', 'executed_by' => $u->id, 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'rehearsal.started', 'rehearsal_id', $id);

            return $r;
        }, protectAdministrativeAccess: false);
        $root = $this->root();
        $client = $root.'/rehearsal-'.$id.'.cnf';
        $db = DB::connection();
        $run = BackupRun::findOrFail($r->backup_run_id);
        try {
            $path = $this->checkFile($run);
            $this->need($db->table('information_schema.SCHEMATA')->where('SCHEMA_NAME', $confirmation)->count() === 0, 'El destino ya existe; no se sobrescribirá.');
            $this->options($db, $client);
            $db->statement('CREATE DATABASE '.$this->ident($confirmation).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            app(SqlBackupClient::class)->restore($client, $path, $confirmation, $root.'/rehearsal-'.$id.'.log');
            config(['database.connections.sga_rehearsal' => array_replace($db->getConfig(), ['database' => $confirmation, 'url' => null])]);
            DB::purge('sga_rehearsal');
            $restored = DB::connection('sga_rehearsal');
            $this->need($restored->selectOne('SELECT DATABASE() AS name')->name === $confirmation, 'Destino incorrecto.');
            $this->need($this->snapshot($restored) === $run->manifest['tables'], 'Los datos o estructuras restaurados no coinciden.');
            $integrity = app(DatabaseIntegrity::class)->assertValid($restored);
            DB::table('backup_restore_rehearsals')->where('id', $id)->update(['status' => 'verified', 'result' => json_encode(['tables_match' => true, 'data_match' => true, 'integrity' => $integrity, 'database_retained' => true]), 'completed_at' => now(), 'updated_at' => now()]);
            app(CriticalAudit::class)->record($actor, 'rehearsal.verified', 'rehearsal_id', $id, ['database_retained' => true]);
        } catch (\Throwable $e) {
            DB::table('backup_restore_rehearsals')->where('id', $id)->update(['status' => 'failed', 'result' => json_encode(['message' => 'Ensayo fallido; conservar destino y registros privados.']), 'completed_at' => now(), 'updated_at' => now()]);
            throw $e;
        } finally {
            if (is_file($client)) {
                unlink($client);
            }
        }
    }
}
