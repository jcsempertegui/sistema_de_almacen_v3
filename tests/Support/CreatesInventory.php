<?php

namespace Tests\Support;

use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Models\DocumentoInventario;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

trait CreatesInventory
{
    private function inventoryContext(string $mode = 'quantity'): array
    {
        return DB::transaction(function () use ($mode) {
            $actor = Usuario::factory()->create(['rol_id' => 1]);
            $producto = Producto::factory()->create(['modo_control' => $mode]);
            $ubicacion = Ubicacion::factory()->create();
            UbicacionProducto::factory()->create(['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id]);
            $unidad = $mode === 'individual' ? UnidadIndividual::factory()->create(['producto_id' => $producto->id, 'ubicacion_id' => null]) : null;

            return compact('actor', 'producto', 'ubicacion', 'unidad');
        });
    }

    private function inventoryData(array $context, string $clase = 'receipt', string $cantidad = '3'): array
    {
        return ['tipo' => $clase, 'fecha_documento' => '2026-10-19', 'fecha_corte' => $clase === 'opening' ? '2026-10-18' : null, 'identificacion_contraparte' => $clase === 'opening' ? null : 'EXT-001', 'nombre_contraparte' => $clase === 'opening' ? null : 'Contraparte de prueba', 'referencia_externa' => null, 'lineas' => [['producto_id' => $context['producto']->id, 'ubicacion_id' => $context['ubicacion']->id, 'cantidad' => $cantidad, 'unit_ids' => $context['unidad'] ? [$context['unidad']->id] : []]], 'expected_line_count' => 1];
    }

    private function inventoryDraft(array $context, string $clase = 'receipt', string $cantidad = '3'): DocumentoInventario
    {
        return app(GuardarDocumentoInventario::class)->handle($context['actor'], null, $this->inventoryData($context, $clase, $cantidad));
    }

    private function postInventory(array $context, string $clase = 'receipt', string $cantidad = '3'): DocumentoInventario
    {
        $documento = $this->inventoryDraft($context, $clase, $cantidad);

        return app(ConfirmarDocumentoInventario::class)->handle($context['actor'], $documento, $documento->version_bloqueo);
    }
}
