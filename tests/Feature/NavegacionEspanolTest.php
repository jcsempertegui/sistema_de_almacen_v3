<?php

namespace Tests\Feature;

use App\Models\Trabajador;
use App\Models\Usuario;
use App\Support\RutasEspanol;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NavegacionEspanolTest extends TestCase
{
    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    public function test_las_rutas_generadas_utilizan_caminos_en_espanol(): void
    {
        foreach (['deliveries' => 'entregas', 'loans' => 'prestamos', 'returns' => 'devoluciones'] as $interno => $publico) {
            $this->assertSame('/operaciones/'.$publico, route('operaciones-trabajadores.index', $interno, false));
            $this->assertSame('/operaciones/'.$publico.'/crear', route('operaciones-trabajadores.create', $interno, false));
        }
        foreach (RutasEspanol::REPORTES as $interno => $publico) {
            $this->assertSame('/reportes/'.$publico, route('reportes-control.index', $interno, false));
        }
        $this->assertSame('/correcciones/ajustes/5/autorizar', route('corrections.transition', ['adjustment', 5, 'approve'], false));
        $this->assertSame('/correcciones/compensaciones/crear', route('corrections.create', 'compensation', false));
        $this->assertSame('/devoluciones/regularizaciones/5/revocar', route('regularizaciones-devolucion.decide', [5, 'revoke'], false));
        $this->assertSame('/ingresar', route('login', [], false));
        $this->assertSame('/cerrar-sesion', route('logout', [], false));
        foreach (['usuarios', 'roles', 'trabajadores', 'productos', 'categorias', 'unidades-medida', 'atributos', 'ubicaciones', 'unidades-individuales', 'documentos-inventario'] as $recurso) {
            $this->assertStringEndsWith('/crear', route($recurso.'.create', [], false));
            $this->assertStringEndsWith('/5/editar', route($recurso.'.edit', 5, false));
        }
        $this->assertSame('/productos/5/ubicaciones/crear', route('ubicaciones-producto.create', 5, false));
    }

    public function test_los_enlaces_antiguos_redirigen_sin_perder_filtros(): void
    {
        foreach (['/login' => '/ingresar', '/operaciones/loans' => '/operaciones/prestamos', '/operaciones/returns/crear' => '/operaciones/devoluciones/crear', '/reportes/stock' => '/reportes/existencias', '/correcciones/adjustment' => '/correcciones/ajustes', '/productos/create' => '/productos/crear', '/trabajadores/5/edit' => '/trabajadores/5/editar'] as $antes => $despues) {
            // URL absoluta para que el cliente de pruebas no traduzca la ruta antes de enviarla.
            $this->get('http://localhost'.$antes.'?q=Ana')->assertRedirect($despues.'?buscar=Ana');
        }
        $this->get('/operaciones/prestamos')->assertRedirect(route('login'));
    }

    public function test_el_menu_lateral_es_la_unica_navegacion_entre_operaciones(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (['deliveries', 'loans', 'returns'] as $clase) {
            foreach (['index', 'create'] as $pantalla) {
                $response = $this->get(route('operaciones-trabajadores.'.$pantalla, $clase))->assertOk();
                $xpath = $this->xpath($response->getContent());
                $this->assertSame(0, $xpath->query('//main//nav[contains(@class,"report-tabs")]')->length);
                $this->assertSame(1, $xpath->query('//aside//a[@aria-current="page"]')->length);
                $this->assertSame(route('operaciones-trabajadores.index', $clase), $xpath->query('//aside//a[@aria-current="page"]')->item(0)->getAttribute('href'));
            }
        }
        foreach (['adjustment', 'compensation'] as $clase) {
            $response = $this->get(route('corrections.index', $clase))->assertOk();
            $this->assertSame(0, $this->xpath($response->getContent())->query('//main//nav[contains(@class,"report-tabs")]')->length);
        }
        // Las consultas relacionadas de reportes no duplican el menú lateral: este agrupa módulos.
        $response = $this->get('/reportes/existencias')->assertOk();
        $this->assertSame(1, $this->xpath($response->getContent())->query('//main//nav[@aria-label="Consultas de este grupo"]')->length);
        $response = $this->get('/reportes/remitos')->assertOk();
        $this->assertSame(0, $this->xpath($response->getContent())->query('//main//nav[@aria-label="Consultas de este grupo"]')->length);
    }

    public function test_las_rutas_traducidas_conservan_validaciones_y_permisos(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post('/operaciones/prestamos', [])->assertSessionHasErrors(['trabajador_id', 'fecha_documento']);
        $this->post('/operaciones/devoluciones', [])->assertSessionHasErrors('prestamo_id');
        $this->post('/correcciones/ajustes', [])->assertSessionHasErrors('fecha_documento');
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        foreach (['entregas', 'prestamos', 'devoluciones'] as $clase) {
            $this->get('/operaciones/'.$clase)->assertOk();
            $this->get('/operaciones/'.$clase.'/crear')->assertForbidden();
            $this->post('/operaciones/'.$clase, [])->assertForbidden();
        }
        $this->get('/operaciones/inexistente')->assertNotFound();
        $this->get('/reportes/inexistente')->assertNotFound();
    }

    public function test_los_filtros_y_la_paginacion_tambien_se_muestran_en_espanol(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $url = route('operaciones-trabajadores.index', ['clase' => 'loans', 'pending' => 1, 'estado' => 'confirmed']);
        $this->assertStringContainsString('pendientes=1', $url);
        $this->assertStringContainsString('estado=confirmado', $url);
        $this->get($url)->assertOk();
        $this->get('http://localhost/operaciones/loans?pending=1&estado=confirmed')->assertRedirect('/operaciones/prestamos?pendientes=1&estado=confirmado');
        $this->get('http://localhost/reportes/stock?format=csv&to=2026-11-05')->assertRedirect('/reportes/existencias?formato=csv&hasta=2026-11-05');

        Trabajador::factory()->count(21)->create(['nombres' => 'Operario', 'activo' => true]);
        $response = $this->get(route('trabajadores.index', ['q' => 'Operario', 'estado' => 'active']))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $siguiente = $xpath->query('//nav[@aria-label="Paginación"]//a[@rel="next"]')->item(0)->getAttribute('href');
        $this->assertStringContainsString('pagina=2', $siguiente);
        $this->assertStringContainsString('buscar=Operario', $siguiente);
        $this->assertStringContainsString('estado=activo', $siguiente);
        $this->get($siguiente)->assertOk()->assertViewHas('trabajadores', fn ($pagina) => $pagina->currentPage() === 2 && $pagina->count() === 1);
        $this->get('/reportes/movimientos?desde=2026-01-01&hasta=2026-11-05')->assertOk()->assertViewHas('filters', fn ($filtros) => $filtros['from'] === '2026-01-01' && $filtros['to'] === '2026-11-05');
        $impresion = route('reportes-control.index', ['clase' => 'stock', 'format' => 'print']);
        $this->assertStringContainsString('formato=imprimir', $impresion);
        $this->get($impresion)->assertOk()->assertViewHas('printing', true);
        $internos = ['units_page' => '2', 'roots_only' => '1', 'tipo' => 'shelf', 'tipo_dato' => 'boolean', 'modo_control' => 'quantity'];
        $publicos = ['pagina_unidades' => '2', 'solo_raices' => '1', 'tipo' => 'estante', 'tipo_dato' => 'booleano', 'modo_control' => 'cantidad'];
        $this->assertSame($publicos, RutasEspanol::consultaPublica($internos));
        $this->assertSame($internos, RutasEspanol::consultaInterna($publicos));
    }

    public function test_el_generador_conserva_firmas_y_no_traduce_texto_libre(): void
    {
        $url = URL::signedRoute('stock.index', ['q' => 'receipt active', 'page' => 2]);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        parse_str(parse_url($url, PHP_URL_QUERY), $consulta);
        $this->assertSame('receipt active', $consulta['buscar']);
        $this->assertSame('2', $consulta['pagina']);
        $this->assertFalse(URL::hasValidSignature(Request::create(str_replace('pagina=2', 'pagina=3', $url))));
        $this->assertSame('https://example.test/report?from=active', URL::to('https://example.test/report?from=active'));
        $this->assertSame(['q' => ['invalido']], RutasEspanol::consultaInterna(['buscar' => ['invalido']]));
    }
}
