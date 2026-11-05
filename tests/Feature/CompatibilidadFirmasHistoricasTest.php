<?php

namespace Tests\Feature;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Trabajador;
use App\Models\UbicacionProducto;
use App\Models\Usuario;
use App\Services\CorreccionesInventario;
use App\Services\HabilitacionDevolucion;
use App\Services\RegularizacionDevolucion;
use App\Support\EsquemaEspanol;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class CompatibilidadFirmasHistoricasTest extends TestCase
{
    use CreatesInventory;

    public static function controles(): array
    {
        return ['cantidad' => ['quantity', '3', '5'], 'individual' => ['individual', '1', '0']];
    }

    /** Proyección SQL independiente de FirmaHistorica, con el orden original de columnas. */
    private function filaAnterior(string $tabla, array $filtro): ?object
    {
        $plan = EsquemaEspanol::diccionario();
        $seleccion = [];
        foreach ($plan['columnas'][$tabla] as $anterior => $actual) {
            $seleccion[] = $actual.' as '.$anterior;
        }

        return DB::table($plan['tablas'][$tabla])->where($filtro)->select($seleccion)->first();
    }

    #[DataProvider('controles')]
    public function test_un_ajuste_solicitado_con_el_contrato_anterior_se_autoriza_y_ejecuta_sin_reescribir_su_firma(string $modo, string $ingreso, string $conteo): void
    {
        $c = $this->inventoryContext($modo);
        $this->postInventory($c, 'receipt', $ingreso);
        $servicio = app(CorreccionesInventario::class);
        $documento = $servicio->save($c['actor'], null, ['tipo' => 'adjustment', 'motivo' => 'Conteo histórico verificado', 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad_contada' => $conteo, 'unit_ids' => $c['unidad'] ? [$c['unidad']->id] : []]]]);
        $linea = $documento->lineas()->firstOrFail();
        $identidades = [];
        if ($c['unidad']) {
            $identidades[] = [
                'identity' => (array) $this->filaAnterior('individual_units', ['id' => $c['unidad']->id]),
                'position' => $this->filaAnterior('individual_unit_positions', ['unidad_individual_id' => $c['unidad']->id]),
                'custody' => $this->filaAnterior('individual_unit_custodies', ['unidad_individual_id' => $c['unidad']->id]),
            ];
        }
        // Contrato JSON original de InventoryCorrections, anterior al cambio de esquema.
        $anterior = ['document' => ['id' => $documento->id, 'kind' => $documento->tipo, 'lock_version' => $documento->version_bloqueo, 'reason' => $documento->motivo, 'document_date' => $documento->fecha_documento, 'source_kind' => $documento->tipo_origen, 'source_id' => $documento->origen_id], 'lines' => [[
            'line' => (array) $this->filaAnterior('inventory_correction_lines', ['id' => $linea->id]),
            'product' => (array) $this->filaAnterior('products', ['id' => $c['producto']->id]),
            'location' => (array) $this->filaAnterior('locations', ['id' => $c['ubicacion']->id]),
            'unit' => (array) $this->filaAnterior('measurement_units', ['id' => $c['producto']->unidad_medida_id]),
            'pair' => $this->filaAnterior('product_locations', ['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id]),
            'stock' => $this->filaAnterior('stock_positions', ['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id]),
            'identities' => $identidades,
        ]]];
        $firmaAnterior = hash('sha256', json_encode($anterior, JSON_THROW_ON_ERROR));
        $servicio->transition($c['actor'], $documento, 'request', 1);
        $autorizacion = DB::table('autorizaciones_correccion')->where('correccion_id', $documento->id)->first();
        $this->assertSame($firmaAnterior, $autorizacion->huella);
        $administrador = Usuario::factory()->create(['rol_id' => 1]);
        $servicio->transition($administrador, $documento, 'approve', 1, 'Se verifica la solicitud histórica');
        $servicio->execute($c['actor'], $documento, 1);
        $this->assertSame($firmaAnterior, DB::table('autorizaciones_correccion')->where('id', $autorizacion->id)->value('huella'));
        $this->assertDatabaseHas('correcciones_inventario', ['id' => $documento->id, 'estado' => 'executed']);
        $this->assertDatabaseHas('existencias_ubicacion', ['producto_id' => $c['producto']->id, 'cantidad' => $conteo.'.000000']);
    }

    public function test_la_regularizacion_conserva_el_contrato_de_firma_anterior_y_no_reactiva_la_herramienta(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        DB::table('unidades_individuales')->where('id', $c['unidad']->id)->update(['activo' => false]);
        $devolucion = app(GuardarDevolucion::class)->handle($c['actor'], null, ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $item = $devolucion->lineas()->firstOrFail();
        $producto = $c['producto']->fresh();
        $unidad = $c['unidad']->fresh();
        $medida = $producto->unidadMedida;
        $ubicacion = $c['ubicacion']->fresh();
        $asociacion = UbicacionProducto::where('producto_id', $producto->id)->where('ubicacion_id', $ubicacion->id)->firstOrFail();
        // Contrato JSON original de ReturnEligibility; no utiliza el conversor de producción.
        $anterior = ['document_id' => $devolucion->id, 'document_version' => $devolucion->version_bloqueo, 'loan_id' => $prestamo->id, 'loan_item_id' => $item->item_prestamo_id, 'individual_unit_id' => $unidad->id, 'product_id' => $producto->id, 'location_id' => $ubicacion->id,
            'product' => ['is_active' => $producto->activo, 'lock_version' => $producto->version_bloqueo, 'measurement_unit_id' => $producto->unidad_medida_id, 'control_mode' => $producto->modo_control, 'is_loanable' => $producto->prestable],
            'unit' => ['is_active' => $unidad->activo, 'lock_version' => $unidad->version_bloqueo, 'product_id' => $unidad->producto_id],
            'measurement' => ['is_active' => $medida->activo, 'symbol' => $medida->simbolo, 'dimension' => $medida->dimension, 'decimal_places' => $medida->decimales],
            'location' => ['is_active' => $ubicacion->activo, 'is_storable' => $ubicacion->permite_almacenar, 'parent_id' => $ubicacion->padre_id, 'lock_version' => $ubicacion->version_bloqueo],
            'pair' => ['id' => $asociacion->id, 'is_active' => $asociacion->activo, 'updated_at' => $asociacion->fecha_actualizacion],
            'custody_cycle_id' => (int) $item->ciclo_custodia_id,
        ];
        $firmaAnterior = hash('sha256', json_encode($anterior, JSON_THROW_ON_ERROR));
        $this->assertSame($firmaAnterior, app(HabilitacionDevolucion::class)->inspect($item->toArray(), $devolucion)['huella']);
        $servicio = app(RegularizacionDevolucion::class);
        $autorizacion = $servicio->request($c['actor'], $item, 1, 'Recepción de una identidad inactiva');
        $this->assertSame($firmaAnterior, $autorizacion->huella);
        $servicio->decide(Usuario::factory()->create(['rol_id' => 1]), $autorizacion, true, 'Destino e identidad verificados');
        app(ConfirmarDevolucion::class)->handle($c['actor'], $devolucion, 1);
        $this->assertDatabaseHas('autorizaciones_regularizacion_devolucion', ['id' => $autorizacion->id, 'huella' => $firmaAnterior, 'estado' => 'used']);
        $this->assertFalse($unidad->fresh()->activo);
    }
}
