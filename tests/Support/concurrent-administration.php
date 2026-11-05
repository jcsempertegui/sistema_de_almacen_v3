<?php

use App\Actions\Roles\GuardarRol;
use App\Actions\Usuarios\ActualizarUsuario;
use App\Models\Permiso;
use App\Models\Usuario;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Standalone subprocess. Reject any destination other than the test database
// before loading Laravel or performing a write.
require dirname(__DIR__).'/bootstrap.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertConnection($app->make('db')->connection());
$actor = Usuario::with('rol')->findOrFail((int) $argv[1]);
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
// Keep the serialization lock briefly so the second independent process
// demonstrably competes for it, rather than only running sequential actions.
$lockWait = 0;
DB::listen(function ($query) use (&$lockWait) {
    if (str_contains($query->sql, '`permisos`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
        $lockWait = max($lockWait, $query->time);
        usleep(300000);
    }
});
try {
    if ($argv[2] === 'usuario') {
        app(ActualizarUsuario::class)->handle($actor, $actor, ['activo' => false]);
    } else {
        $ids = Permiso::where('codigo', '!=', 'users.update')->pluck('id')->all();
        app(GuardarRol::class)->handle($actor, $actor->rol, ['permisos' => $ids]);
    }
    echo "RESULT:changed\n";
} catch (ValidationException) {
    echo "RESULT:protected\n";
}
echo 'LOCK_WAIT:'.$lockWait."\n";
