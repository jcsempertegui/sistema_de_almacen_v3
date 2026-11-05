<?php

namespace Tests\Feature;

use App\Models\Trabajador;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class SearchableSelectorsTest extends TestCase
{
    use CreatesInventory;

    private function guardarEscenarioNavegador(string $nombre, string $html): void
    {
        $directorio = getenv('SGA_SELECTORES_EVIDENCE');
        if (! $directorio) {
            return;
        }
        $ruta = realpath($directorio);
        $this->assertNotFalse($ruta);
        $this->assertStringStartsNotWith(strtolower(str_replace('\\', '/', base_path())).'/', strtolower(str_replace('\\', '/', $ruta)).'/');
        $publico = 'file:///'.str_replace('\\', '/', public_path());
        foreach (['css/sga.css', 'js/sga-ui.js', 'js/sga-selectores.js', 'images/confipetrol.png'] as $asset) {
            $html = str_replace(asset($asset), $publico.'/'.$asset, $html);
        }
        $proteccion = '<script>window.erroresPrueba=[];window.addEventListener("error",e=>window.erroresPrueba.push(e.message));document.addEventListener("submit",e=>e.preventDefault(),true);</script>';
        $html = str_replace('<head>', '<head>'.$proteccion, $html);
        $script = '<script>window.escenarioPrueba="'.$nombre.'";</script><script defer src="file:///'.str_replace('\\', '/', $ruta).'/comprobar-selectores.js"></script>';
        file_put_contents($ruta.'/'.$nombre.'.html', str_replace('</body>', $script.'</body>', $html));
    }

    public function test_delivery_form_keeps_authorized_native_fields_and_enables_shared_search_without_writes(): void
    {
        $c = $this->inventoryContext();
        $worker = Trabajador::factory()->create(['nombres' => 'José Carlos', 'apellidos' => 'Mendoza Rojas']);
        $response = $this->signIn($c['actor'])->get(route('operaciones-trabajadores.create', 'deliveries'))->assertOk();
        $response->assertSee('js/sga-selectores.js', false)->assertSee('name="trabajador_id"', false)->assertSee('José Carlos Mendoza Rojas');
        $this->assertStringNotContainsString('data-select-search=', $response->getContent());
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertTrue($worker->activo);
        $this->guardarEscenarioNavegador('entrega', $response->getContent());
    }

    public function test_inventory_and_adjustment_forms_keep_server_names_and_safe_dynamic_row_scripts(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        $response = $this->get(route('documentos-inventario.create', ['tipo' => 'receipt']));
        $response->assertOk();
        $this->assertStringNotContainsString('documento.getElementById', $response->getContent());
        $this->assertStringNotContainsString('.hijos', $response->getContent());
        $this->guardarEscenarioNavegador('remito', $response->getContent());
        $response = $this->get(route('corrections.create', 'adjustment'))->assertOk();
        $this->assertStringNotContainsString('[nombre]', $response->getContent());
        $this->guardarEscenarioNavegador('ajuste', $response->getContent());
        $this->assertSame(0, DB::table('movimientos_inventario')->count());
    }

    public function test_individual_unit_form_preserves_product_location_dependencies(): void
    {
        $primero = $this->inventoryContext('individual');
        $segundo = $this->inventoryContext('individual');
        $response = $this->signIn($primero['actor'])->get(route('unidades-individuales.create'))->assertOk();
        $response->assertSee('data-producto="'.$primero['producto']->id.'"', false)
            ->assertSee('data-producto="'.$segundo['producto']->id.'"', false);
        $this->assertStringNotContainsString('documento.getElementById', $response->getContent());
        $this->guardarEscenarioNavegador('unidad', $response->getContent());
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
