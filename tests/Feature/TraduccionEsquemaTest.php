<?php

namespace Tests\Feature;

use App\Casts\DatosHistoricosEnEspanol;
use App\Models\Usuario;
use App\Services\RespaldosBaseDatos;
use App\Support\EsquemaEspanol;
use App\Support\TestingDatabaseGuard;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class TraduccionEsquemaTest extends TestCase
{
    public function test_el_diccionario_coincide_con_las_tablas_y_columnas_reales(): void
    {
        $plan = EsquemaEspanol::diccionario();
        foreach ($plan['tablas'] as $antes => $despues) {
            $columnas = DB::select('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$despues]);
            $this->assertSame(array_values($plan['columnas'][$antes]), array_column($columnas, 'COLUMN_NAME'), $despues);
        }
        $this->assertSame(53, (int) DB::selectOne("SELECT COUNT(*) AS cantidad FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->cantidad);
        $this->assertSame(count(glob(database_path('migrations/*.php'))), DB::table('migrations')->count());
        $this->assertSame(123, DB::table('permisos')->count());
        $this->assertSame(260, DB::table('permiso_rol')->count());
    }

    public function test_la_migracion_rechaza_una_conexion_principal_antes_de_abrir_pdo(): void
    {
        $abierta = false;
        $conexion = new MySqlConnection(function () use (&$abierta) {
            $abierta = true;
            throw new RuntimeException('No debe abrirse la conexión.');
        }, 'sga_rio_grande', '', ['name' => TestingDatabaseGuard::CONNECTION, 'driver' => 'mysql']);
        try {
            EsquemaEspanol::aplicar($conexion);
            $this->fail('No debe autorizarse la base principal.');
        } catch (RuntimeException $e) {
            $this->assertSame('Aislamiento de testing rechazado antes de escribir.', $e->getMessage());
        }
        $this->assertFalse($abierta);
    }

    public function test_las_claves_historicas_se_leen_en_espanol_sin_reescribir_el_original(): void
    {
        $original = '{"attribute":{"name":"Presión","data_type":"decimal"},"unit_symbol":"bar","is_active":true,"category_attribute":{"is_required":true},"measurement_unit":{"symbol":"bar"}}';
        $modelo = new Usuario;
        $modelo->setRawAttributes(['datos_historicos' => $original], true);
        $resultado = (new DatosHistoricosEnEspanol)->get($modelo, 'datos_historicos', $modelo->getRawOriginal('datos_historicos'), []);
        $this->assertSame('Presión', $resultado['atributo']['nombre']);
        $this->assertSame('decimal', $resultado['atributo']['tipo_dato']);
        $this->assertSame('bar', $resultado['simbolo_unidad']);
        $this->assertTrue($resultado['activo']);
        $this->assertTrue($resultado['atributo_categoria']['obligatorio']);
        $this->assertSame('bar', $resultado['unidad_medida']['simbolo']);
        $this->assertSame($original, $modelo->getRawOriginal('datos_historicos'));
    }

    public function test_la_traduccion_conserva_espacios_en_la_contrasena(): void
    {
        $usuario = Usuario::factory()->create(['contrasena' => ' ClaveSegura123! ']);
        $this->post(route('login.store'), ['correo' => $usuario->correo, 'contrasena' => ' ClaveSegura123! '])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_archivo_del_cliente_mariadb_conserva_sus_claves_obligatorias(): void
    {
        $archivo = sys_get_temp_dir().'/sga-opciones-'.bin2hex(random_bytes(8)).'.cnf';
        $conexion = new class
        {
            public function getConfig($clave): string
            {
                return ['host' => '127.0.0.1', 'port' => '3306', 'username' => 'usuario_ficticio', 'password' => 'clave_ficticia'][$clave];
            }
        };
        try {
            (new ReflectionMethod(RespaldosBaseDatos::class, 'options'))->invoke(app(RespaldosBaseDatos::class), $conexion, $archivo);
            $contenido = file_get_contents($archivo);
            $this->assertStringContainsString('user="usuario_ficticio"', $contenido);
            $this->assertStringContainsString('password="clave_ficticia"', $contenido);
            $this->assertStringNotContainsString('contrasena=', $contenido);
        } finally {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }
    }
}
