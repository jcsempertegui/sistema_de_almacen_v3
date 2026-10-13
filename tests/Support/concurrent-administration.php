<?php

use App\Actions\Roles\SaveRole;
use App\Actions\Users\UpdateUser;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Standalone subprocess. Reject any destination other than the test database
// before loading Laravel or performing a write.
require dirname(__DIR__, 2).'/vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'mysql'
    || getenv('DB_DATABASE') !== 'sga_rio_grande_testing' || getenv('DB_URL')
    || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Destino de concurrencia no autorizado.');
}
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname=sga_rio_grande_testing', getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sga_rio_grande_testing') {
    throw new RuntimeException('La conexión de concurrencia no está aislada.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || $app->make('db')->connection()->selectOne('SELECT DATABASE() AS name')->name !== 'sga_rio_grande_testing') {
    throw new RuntimeException('Laravel no conserva el aislamiento.');
}
$actor = User::with('role')->findOrFail((int) $argv[1]);
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
// Keep the serialization lock briefly so the second independent process
// demonstrably competes for it, rather than only running sequential actions.
$lockWait = 0;
DB::listen(function ($query) use (&$lockWait) {
    if (str_contains($query->sql, '`permissions`') && str_contains($query->sql, 'for update')) {
        $lockWait = $query->time;
        usleep(300000);
    }
});
try {
    if ($argv[2] === 'user') {
        app(UpdateUser::class)->handle($actor, $actor, ['is_active' => false]);
    } else {
        $ids = Permission::where('code', '!=', 'users.update')->pluck('id')->all();
        app(SaveRole::class)->handle($actor, $actor->role, ['permissions' => $ids]);
    }
    echo "RESULT:changed\n";
} catch (ValidationException) {
    echo "RESULT:protected\n";
}
echo 'LOCK_WAIT:'.$lockWait."\n";
