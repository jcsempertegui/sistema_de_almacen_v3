<?php

namespace Tests\Feature\Inventory;

use App\Actions\Entregas\ConfirmarEntrega;
use App\Actions\Entregas\GuardarEntrega;
use App\Models\Trabajador;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class WorkerOperationSchemaTest extends TestCase
{
    use CreatesInventory;

    public function test_position_rejects_multiple_sources_and_missing_source(): void
    {
        $c = $this->inventoryContext('individual');
        $old = $this->postInventory($c, 'receipt', '1');
        $doc = app(GuardarEntrega::class)->handle($c['actor'], null, ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad' => '1', 'unit_ids' => [$c['unidad']->id]]]]);
        app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 1);
        foreach ([['ultima_linea_documento_id' => $old->lineas()->first()->id], ['ultima_linea_entrega_id' => null]] as $invalid) {
            try {
                DB::table('posiciones_unidad_individual')->where('unidad_individual_id', $c['unidad']->id)->update($invalid);
                $this->fail('Invalid position origen accepted');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->errorInfo[0]);
            }
        }
    }

    public function test_sources_are_exclusive_unique_and_directional_with_composite_membership(): void
    {
        $c = $this->inventoryContext();
        $old = $this->postInventory($c);
        $trabajador = Trabajador::factory()->create();
        $id = DB::table('entregas_materiales')->insertGetId(['uuid' => (string) Str::uuid(), 'trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-19']);
        $line = (array) DB::table('lineas_documento_inventario')->where('documento_id', $old->id)->first();
        unset($line['id'],$line['documento_id']);
        $line['entrega_id'] = $id;
        $origen = DB::table('lineas_entrega_material')->insertGetId($line);
        $movement = ['linea_entrega_id' => $origen, 'producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'direccion' => 'out', 'cantidad' => '1', 'creado_por' => $c['actor']->id, 'fecha_registro' => now()];
        foreach ([['linea_entrega_id' => null], ['linea_documento_id' => $old->lineas()->first()->id], ['direccion' => 'in'], ['ubicacion_id' => 999999999]] as $invalid) {
            try {
                DB::table('movimientos_inventario')->insert(array_replace($movement, $invalid));
                $this->fail('SQL accepted invalid origen');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->errorInfo[0]);
            }
        }
        DB::table('movimientos_inventario')->insert($movement);
        $this->expectException(QueryException::class);
        DB::table('movimientos_inventario')->insert($movement);
    }
}
