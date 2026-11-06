<?php

// Diagnóstico de la instalación: únicamente SELECT en una transacción de solo lectura.
use App\Services\IntegridadBaseDatos;
use App\Services\ModificacionSegura;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
try {
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $db = $app->make('db')->connection();
    if ($app->environment('testing') || $db->getDatabaseName() !== 'sga_rio_grande'
        || $db->getDriverName() !== 'mysql' || ! in_array($db->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('El diagnóstico requiere la principal local verificada; no admite un destino por argumento.');
    }
    $db->statement('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $identidad = (array) $db->selectOne('SELECT DATABASE() AS base, @@hostname AS servidor, @@port AS puerto, @@datadir AS directorio, VERSION() AS version');
        if ($identidad['base'] !== 'sga_rio_grande') {
            throw new RuntimeException('La identidad SQL no coincide con la configuración.');
        }
        $archivos = array_map(fn ($ruta) => basename($ruta, '.php'), glob(dirname(__DIR__).'/database/migrations/*.php'));
        $aplicadas = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
        $conteos = [];
        foreach (['usuarios', 'trabajadores', 'productos', 'unidades_individuales', 'permisos', 'permiso_rol', 'movimientos_inventario', 'entregas_materiales', 'prestamos_herramientas', 'devoluciones_herramientas'] as $tabla) {
            $conteos[$tabla] = $db->table($tabla)->count();
        }
        $saldo = $db->table('movimientos_inventario')->select('producto_id', 'ubicacion_id')
            ->selectRaw("SUM(CASE WHEN direccion = 'in' THEN cantidad ELSE -cantidad END) AS cantidad")
            ->groupBy('producto_id', 'ubicacion_id');
        $diferencias = $db->table('existencias_ubicacion as e')->leftJoinSub(clone $saldo, 'm', fn ($join) => $join->on('m.producto_id', '=', 'e.producto_id')->on('m.ubicacion_id', '=', 'e.ubicacion_id'))
            ->whereRaw('e.cantidad <> COALESCE(m.cantidad, 0)')->count();
        $faltantes = $db->query()->fromSub($saldo, 'm')->leftJoin('existencias_ubicacion as e', fn ($join) => $join->on('m.producto_id', '=', 'e.producto_id')->on('m.ubicacion_id', '=', 'e.ubicacion_id'))
            ->whereNull('e.producto_id')->where('m.cantidad', '<>', 0)->count();
        $integridad = app(IntegridadBaseDatos::class)->assertValid($db);
        $administracion = app(ModificacionSegura::class)->hasAdministrativeAccess();
        $resultado = [
            'fecha' => (new DateTimeImmutable('now', new DateTimeZone('America/La_Paz')))->format(DATE_ATOM),
            'php' => PHP_VERSION, 'laravel' => $app->version(), 'identidad' => $identidad,
            'tablas' => (int) $db->selectOne("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->n,
            'migraciones' => count($aplicadas), 'pendientes' => array_values(array_diff($archivos, $aplicadas)),
            'migraciones_sin_archivo' => array_values(array_diff($aplicadas, $archivos)), 'conteos' => $conteos,
            'administracion_activa' => $administracion, 'integridad_sql' => $integridad,
            'diferencias_saldo_movimientos' => $diferencias, 'saldos_faltantes' => $faltantes,
            'existencias_negativas' => $db->table('existencias_ubicacion')->where('cantidad', '<', 0)->count(),
            'aceptacion_humana' => 'No evaluada por este diagnóstico',
        ];
        if ($diferencias || $faltantes || ! $administracion || $resultado['existencias_negativas'] || $resultado['pendientes'] || $resultado['migraciones_sin_archivo']) {
            throw new RuntimeException('El cierre requiere revisar administración, migraciones o conciliación; no se aplicaron reparaciones.');
        }
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    } finally {
        $db->rollBack();
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'No se certificó el cierre: '.$error->getMessage().PHP_EOL);
    exit(1);
}
