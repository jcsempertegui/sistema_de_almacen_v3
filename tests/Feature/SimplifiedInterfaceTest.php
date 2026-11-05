<?php

namespace Tests\Feature;

use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Permiso;
use App\Models\Trabajador;
use App\Models\Usuario;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class SimplifiedInterfaceTest extends TestCase
{
    use CreatesInventory;

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    public function test_nine_sections_and_four_daily_actions_keep_secondary_options_collapsed(): void
    {
        $response = $this->signIn(Usuario::factory()->create(['rol_id' => 1]))->get(route('dashboard'))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(9, $xpath->query('//aside//nav/section')->length);
        $this->assertSame(4, $xpath->query('//aside//details[not(@open)]')->length);
        $this->assertSame(4, $xpath->query('//main//div[contains(@class,"daily-primary")]/a')->length);
        foreach (['deliveries', 'loans', 'returns'] as $clase) {
            $this->assertSame(1, $xpath->query('//main//div[contains(@class,"daily-primary")]/a[@href="'.route('operaciones-trabajadores.create', $clase).'"]')->length);
        }
    }

    public function test_active_tools_section_opens_and_read_only_users_cannot_see_creation_shortcuts(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        $response = $this->get(route('operaciones-trabajadores.index', 'returns'))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//aside//section[@aria-label="Herramientas"]/details[@open]')->length);
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $this->xpath($dashboard->getContent())->query('//main//div[contains(@class,"daily-primary")]/a')->length);
        $this->get(route('operaciones-trabajadores.create', 'returns'))->assertForbidden();
    }

    public function test_secondary_report_filters_preserve_values_and_open_when_applied(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        $response = $this->get(route('reportes-control.index', 'stock'))->assertOk();
        $this->assertSame(0, $this->xpath($response->getContent())->query('//details[summary="Más filtros"]/@open')->length);
        $response = $this->get(route('reportes-control.index', ['clase' => 'stock', 'producto_id' => $c['producto']->id]))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//details[summary="Más filtros"]/@open')->length);
        $this->assertSame((string) $c['producto']->id, $xpath->query('//select[@name="producto_id"]/option[@selected]')->item(0)->getAttribute('value'));
    }

    public function test_product_form_keeps_optional_inputs_and_reopens_on_validation_errors(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor'])->post(route('productos.preview-update', $c['producto']), array_replace($c['producto']->getAttributes(), ['values' => [], 'marca' => str_repeat('x', 101)]))->assertSessionHasErrors('marca');
        $response = $this->get(route('productos.edit', $c['producto']))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//details[summary="Marca y referencia (opcional)"][@open]//input[@name="marca"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="referencia_fabricante"]')->length);
        $this->assertSame(1, $xpath->query('//main//form[@method="POST"]//input[@name="_token"]')->length);
    }

    public function test_return_shortcut_selects_only_pending_confirmed_loan_without_writing(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $this->signIn($c['actor']);
        $url = route('operaciones-trabajadores.create', ['clase' => 'returns', 'prestamo_id' => $prestamo->id]);
        $this->get($url)->assertNotFound();
        $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        $before = DB::table('movimientos_inventario')->count();
        $response = $this->get($url)->assertOk();
        $this->assertSame((string) $prestamo->id, $this->xpath($response->getContent())->query('//select[@name="prestamo_id"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame($before, DB::table('movimientos_inventario')->count());
        $this->assertSame(0, DB::table('devoluciones_herramientas')->count());
        $this->get(route('operaciones-trabajadores.show', ['loans', $prestamo->id]))->assertOk()->assertSee('Registrar devolución de este préstamo');
        DB::table('permiso_rol')->where('rol_id', 1)->where('permiso_id', Permiso::where('codigo', 'returns.create')->value('id'))->update(['activo' => false]);
        $this->get($url)->assertForbidden();
        $this->get(route('operaciones-trabajadores.show', ['loans', $prestamo->id]))->assertOk()->assertDontSee('Registrar devolución de este préstamo');
    }
}
