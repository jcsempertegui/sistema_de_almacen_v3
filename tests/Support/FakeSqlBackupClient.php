<?php

namespace Tests\Support;

use App\Services\SqlBackupClient;
use Illuminate\Support\Facades\DB;

// Orchestration fixture, not evidence that a real MariaDB client was executed.
class FakeSqlBackupClient extends SqlBackupClient
{
    public bool $fail = false;

    public function dump(string $options, string $sql, string $database, string $logs): void
    {
        if ($database !== 'sga_rio_grande_testing' || DB::selectOne('SELECT DATABASE() AS name')->name !== $database) {
            throw new \RuntimeException('Isolation rejected');
        }
        if ($this->fail) {
            throw new \RuntimeException('Injected dump failure');
        }$out = '';
        foreach (DB::select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            $out .= array_values((array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`'))[1].";\n";
            foreach (DB::table($table)->get() as $data) {
                $a = (array) $data;
                $keys = implode(',', array_map(fn ($k) => '`'.$k.'`', array_keys($a)));
                $values = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : DB::connection()->getPdo()->quote((string) $v), array_values($a)));
                $out .= 'INSERT INTO `'.$table.'` ('.$keys.') VALUES ('.$values.");\n";
            }
        }
        $out .= "-- Dump completed on testing fixture\n";
        file_put_contents($sql, $out);
    }
}
