<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    private static bool $schemaPrepared = false;

    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();
        if (! $app->environment('testing') || $connection->getDatabaseName() !== 'sga_rio_grande_testing' || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'sga_rio_grande_testing') {
            throw new \RuntimeException('Se bloqueó el acceso a una base ajena a las pruebas.');
        }
        if (! self::$schemaPrepared) {
            if ($app->make(Kernel::class)->call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException('No se pudo preparar el esquema de pruebas.');
            }
            self::$schemaPrepared = true;
        }

        return $app;
    }

    protected function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->session_version]);
    }
}
