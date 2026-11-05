<?php

namespace App\Services;

use App\Models\DevolucionHerramienta;
use App\Models\ItemPrestamoHerramienta;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\UnidadMedida;
use App\Support\FirmaHistorica;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HabilitacionDevolucion
{
    public function inspect(array $item, DevolucionHerramienta $documento): array
    {
        $itemPrestamo = ItemPrestamoHerramienta::whereKey($item['item_prestamo_id'])->lockForUpdate()->firstOrFail();
        if ($itemPrestamo->prestamo_id !== $documento->prestamo_id || $itemPrestamo->unidad_individual_id !== (int) $item['unidad_individual_id'] || $itemPrestamo->producto_id !== (int) $item['producto_id']) {
            $this->fail('El ítem no pertenece al préstamo.');
        }
        $custody = DB::table('custodias_unidad_individual')->where('unidad_individual_id', $itemPrestamo->unidad_individual_id)->lockForUpdate()->first();
        $cycles = app(CiclosPrestamo::class);
        if ($cycles->enabled()) {
            $cycle = $cycles->current($itemPrestamo->unidad_individual_id, $itemPrestamo->id);
            if ((int) ($item['ciclo_custodia_id'] ?? 0) !== $cycle->id) {
                $this->fail('El ciclo de custodia cambió; prepara nuevamente la devolución.');
            }
        }
        if ((int) ($custody?->item_prestamo_actual_id ?? 0) !== $itemPrestamo->id || (! $cycles->enabled() && DB::table('cierres_item_prestamo')->where('item_prestamo_id', $itemPrestamo->id)->lockForUpdate()->first())) {
            $this->fail('La herramienta no tiene este préstamo pendiente.');
        }
        $producto = Producto::whereKey($itemPrestamo->producto_id)->lockForUpdate()->firstOrFail();
        $unidad = UnidadIndividual::whereKey($itemPrestamo->unidad_individual_id)->lockForUpdate()->firstOrFail();
        $measure = UnidadMedida::whereKey($itemPrestamo->unidad_medida_id)->lockForUpdate()->firstOrFail();
        if ($producto->modo_control !== 'individual' || ! $producto->prestable || $unidad->producto_id !== $producto->id || $producto->unidad_medida_id !== $measure->id || $measure->dimension !== 'count' || $measure->decimales !== 0 || $measure->simbolo !== $itemPrestamo->simbolo_unidad) {
            $this->fail('La definición histórica de la herramienta es incompatible; no puede regularizarse reinterpretando cantidades.');
        }
        $ubicacion = Ubicacion::whereKey($item['ubicacion_id'])->lockForUpdate()->first();
        if (! $ubicacion?->activo || ! $ubicacion->permite_almacenar) {
            $this->fail('El destino debe estar activo y permitir almacenamiento.');
        }
        app(JerarquiaUbicaciones::class)->validateParent($ubicacion, true);
        $pair = UbicacionProducto::where('producto_id', $producto->id)->where('ubicacion_id', $ubicacion->id)->lockForUpdate()->first();
        if (! $pair) {
            $this->fail('Debe existir la asociación producto–destino. No se crea automáticamente.');
        }
        $snapshot = ['documento_id' => $documento->id, 'version_documento' => $documento->version_bloqueo, 'prestamo_id' => $documento->prestamo_id, 'item_prestamo_id' => $itemPrestamo->id, 'unidad_individual_id' => $unidad->id, 'producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id,
            'producto' => $producto->only(['activo', 'version_bloqueo', 'unidad_medida_id', 'modo_control', 'prestable']),
            'unidad' => $unidad->only(['activo', 'version_bloqueo', 'producto_id']), 'measurement' => $measure->only(['activo', 'simbolo', 'dimension', 'decimales']),
            'ubicacion' => $ubicacion->only(['activo', 'permite_almacenar', 'padre_id', 'version_bloqueo']), 'pair' => $pair->only(['id', 'activo', 'fecha_actualizacion'])];
        if ($cycles->enabled()) {
            $snapshot['ciclo_custodia_id'] = $cycle->id;
        }
        $exceptions = [];
        foreach (['producto' => $producto, 'unidad' => $unidad, 'measurement' => $measure, 'pair' => $pair] as $key => $reference) {
            if (! $reference->activo) {
                $exceptions[] = $key;
            }
        }

        // Las claves de esta firma son un contrato hist?rico, no identificadores SQL nuevos.
        $firma = ['document_id' => $documento->id, 'document_version' => $documento->version_bloqueo, 'loan_id' => $documento->prestamo_id, 'loan_item_id' => $itemPrestamo->id, 'individual_unit_id' => $unidad->id, 'product_id' => $producto->id, 'location_id' => $ubicacion->id,
            'product' => FirmaHistorica::campos('products', $snapshot['producto']),
            'unit' => FirmaHistorica::campos('individual_units', $snapshot['unidad']),
            'measurement' => FirmaHistorica::campos('measurement_units', $snapshot['measurement']),
            'location' => FirmaHistorica::campos('locations', $snapshot['ubicacion']),
            'pair' => FirmaHistorica::campos('product_locations', $snapshot['pair'])];
        if ($cycles->enabled()) {
            $firma['custody_cycle_id'] = $cycle->id;
        }

        return ['snapshot' => $snapshot, 'huella' => hash('sha256', json_encode($firma, JSON_THROW_ON_ERROR)), 'exceptions' => $exceptions];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['lineas' => $message]);
    }
}
