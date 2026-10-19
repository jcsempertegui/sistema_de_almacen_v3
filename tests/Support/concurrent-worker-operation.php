<?php

use App\Actions\Catalog\SaveIndividualUnit;
use App\Actions\Deliveries\ConfirmDelivery;
use App\Actions\Loans\ConfirmLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Roles\SaveRole;
use App\Actions\Workers\SaveWorker;
use App\Models\IndividualUnit;
use App\Models\Permission;
use App\Models\ReturnRegularizationAuthorization;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Services\ReturnRegularization;
use App\Support\WorkerOperationTypes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'sga_rio_grande_testing' || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_URL') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Isolation rejected');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::selectOne('SELECT DATABASE() AS db')->db !== 'sga_rio_grande_testing') {
    throw new RuntimeException('Isolation rejected');
}
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$actor = User::with('role')->findOrFail($job['actor']);
DB::beginTransaction();
DB::table('stock_positions')->get();
$class = WorkerOperationTypes::MODELS[$job['kind']];
$doc = $class::findOrFail($job['document']);
$wait = 0;
DB::listen(function ($query) use ($job, &$wait) {
    if (str_contains($query->sql, '`permissions`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
        $wait = max($wait, $query->time);
        echo "LOCKED\n";
        flush();
        if ($job['index'] === 0 && trim((string) fgets(STDIN)) !== 'RELEASE') {
            throw new RuntimeException('Missing release');
        }
    }
});
echo 'CONNECTION_ID:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\nREADY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
echo "GO_RECEIVED\n";
flush();
try {
    if ($job['operation'] === 'revoke') {
        app(SaveRole::class)->handle($actor, Role::findOrFail($job['role']), ['permissions' => Permission::where('code', '<>', $job['kind'].'.confirm')->pluck('id')->all()]);
    } elseif ($job['operation'] === 'disable_worker') {
        app(SaveWorker::class)->handle($actor, Worker::findOrFail($doc->worker_id), ['is_active' => false], true);
    } elseif ($job['operation'] === 'disable_unit') {
        app(SaveIndividualUnit::class)->handle($actor, IndividualUnit::findOrFail($doc->lines()->first()->individual_unit_id), ['is_active' => false, 'lock_version' => 1], true);
    } elseif ($job['operation'] === 'revoke_regularization') {
        app(ReturnRegularization::class)->decide($actor, ReturnRegularizationAuthorization::findOrFail($job['authorization']), false, 'Revocación concurrente');
    } else {
        $action = ['deliveries' => ConfirmDelivery::class, 'loans' => ConfirmLoan::class, 'returns' => ConfirmReturn::class][$job['kind']];
        app($action)->handle($actor, $doc, 1);
    }
    DB::commit();
    echo "RESULT:changed\n";
} catch (AuthorizationException|ValidationException $e) {
    DB::rollBack();
    echo "RESULT:protected\n";
} catch (HttpException $e) {
    DB::rollBack();
    if ($e->getStatusCode() !== 409) {
        throw $e;
    }echo "RESULT:protected\n";
}
echo 'LOCK_WAIT:'.$wait."\n";
