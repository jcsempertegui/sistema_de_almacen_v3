<?php

namespace App\Services;

use App\Actions\Catalogo\GuardarAtributoCatalogo;
use App\Actions\Catalogo\GuardarAtributoCategoria;
use App\Actions\Catalogo\GuardarCategoria;
use App\Actions\Catalogo\GuardarProducto;
use App\Actions\Catalogo\GuardarUbicacion;
use App\Actions\Catalogo\GuardarUbicacionProducto;
use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Actions\Catalogo\GuardarUnidadMedida;
use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Entregas\ConfirmarEntrega;
use App\Actions\Entregas\GuardarEntrega;
use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Actions\Trabajadores\GuardarTrabajador;
use App\Actions\Usuarios\CrearUsuario;
use App\Models\PrestamoHerramienta;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\OperacionLocalAutorizada;
use App\Support\TestingDatabaseGuard;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit, one-time fictional history. Never registered as a general seeder. */
class EscenarioAnualPlanta
{
    public const MARKER = '[SIMULACIÓN ANUAL RG]';

    private array $productos = [];

    private array $trabajadores = [];

    private array $unidades = [];

    private array $ubicaciones = [];

    private array $credentials = [];

    private Usuario $operator;

    private Usuario $administrator;

    private CarbonImmutable $start;

    public function run(Usuario $administrator, CarbonImmutable $end, array $expectedIdentity, ?Closure $progress = null, bool $allowPrimary = false): array
    {
        $identity = TestingDatabaseGuard::identity(DB::connection()->getPdo());
        if ($identity !== $expectedIdentity || (! in_array($identity['database_name'], ['sga_rio_grande', TestingDatabaseGuard::DATABASE], true) && ! OperacionLocalAutorizada::vigente(DB::connection()))
            || ($identity['database_name'] === 'sga_rio_grande' && ! $allowPrimary)) {
            throw new RuntimeException('Destino no autorizado para la simulación.');
        }
        if ($identity['database_name'] === TestingDatabaseGuard::DATABASE) {
            TestingDatabaseGuard::assertConnection(DB::connection());
        }
        if ($end->toDateString() > CarbonImmutable::now('America/La_Paz')->toDateString()) {
            throw new RuntimeException('El período no puede terminar en el futuro.');
        }
        $this->start = $end->subYear()->addDay()->startOfDay();
        $this->administrator = $administrator;
        $oldClock = Date::getTestNow();
        $this->productos = $this->trabajadores = $this->unidades = $this->ubicaciones = $this->credentials = [];
        try {
            return DB::transaction(function () use ($administrator, $end, $progress) {
                app(ModificacionSegura::class)->exigirPermisos($administrator, 'users.create', 'users.assign_role', 'products.create', 'initial_stock.confirm');
                // Shared mutex also prevents two parallel loaders from passing the empty-state checks.
                DB::table('permisos')->where('codigo', 'roles.assign_permissions')->lockForUpdate()->firstOrFail();
                foreach (['trabajadores', 'categorias', 'unidades_medida', 'ubicaciones', 'productos', 'documentos_inventario', 'movimientos_inventario', 'entregas_materiales', 'prestamos_herramientas', 'devoluciones_herramientas'] as $table) {
                    if (DB::table($table)->exists()) {
                        throw new RuntimeException('La carga requiere catálogo y operaciones vacíos; se conserva todo lo existente: '.$table);
                    }
                }
                $this->clock($this->start, 6);
                $this->people($administrator);
                $this->catalog($administrator);
                $this->clock($this->start, 7);
                $opening = [];
                foreach ($this->productos as $key => $entry) {
                    $opening[] = $this->line($key, $entry['opening'], $entry['unidades']);
                }
                $this->inventory('opening', $opening, $this->start, 'Saldo físico inicial del contrato; escenario ficticio');

                $permanent = [];
                $monthly = [];
                for ($day = $this->start, $offset = 0; $day->lte($end); $day = $day->addDay(), $offset++) {
                    $month = $this->start->diffInMonths($day->startOfMonth());
                    if ($day->day === 1 && $offset > 0) {
                        $this->clock($day, 8);
                        $this->inventory('receipt', [
                            $this->line('GUANTE-NIT', 80), $this->line('GUANTE-VAQ', 50),
                            $this->line('RESP-PART', 80), $this->line('TAPON', 100),
                            $this->line('FILTRO', 30), $this->line('LENTE', 30),
                        ], $day, 'Reposición mensual de EPP consumible');
                    }
                    if ($offset === 0 || $day->equalTo($this->start->addMonths(6))) {
                        $this->clock($day, 9);
                        foreach ($this->trabajadores as $i => $trabajador) {
                            $size = ['M', 'L', 'XL', 'XXL'][$i % 4];
                            $shoe = (string) (39 + $i % 4);
                            $this->delivery($trabajador->id, [
                                $this->line('OVEROL-'.$size, 1), $this->line('CAMISA-'.$size, 2),
                                $this->line('PANTALON-'.$size, 2), $this->line('BOTIN-'.$shoe, 1),
                                $this->line('CASCO', 1), $this->line('LENTE', 1),
                            ], $day, 'Dotación semestral de EPP; talla '.$size.', calzado '.$shoe);
                        }
                    }
                    if ($offset === 1) {
                        $this->clock($day, 10);
                        foreach (array_slice($this->trabajadores, 0, 20) as $i => $trabajador) {
                            $types = $i < 6 ? ['LLAVE', 'COMBINADAS'] : ($i < 10 ? ['MULTIMETRO', 'ALICATE'] : ($i < 14 ? ['CALIBRADOR', 'MULTIMETRO'] : ($i < 18 ? ['DESTORNILLADOR', 'ALICATE'] : ['AMOLADORA', 'LLAVE'])));
                            $ids = array_map(fn ($tipo) => array_shift($this->unidades[$tipo]), $types);
                            $permanent[] = $this->prestamo($trabajador->id, $ids, $day, $i < 4 ? $end->subDays(16)->toDateString() : null, 'Asignación de herramientas para actividades del contrato');
                        }
                    }
                    $this->clock($day, 12);
                    for ($request = 0; $request < 1 + $offset % 3; $request++) {
                        $trabajador = $this->trabajadores[($offset * 3 + $request) % 20];
                        $glove = ($offset + $request) % 3 === 0 ? 'GUANTE-VAQ' : 'GUANTE-NIT';
                        $extra = ['RESP-PART', 'TAPON', 'FILTRO', 'LENTE'][($offset + $request) % 4];
                        $lineas = [$this->line($glove, 1), $this->line($extra, 1)];
                        if ($request === 0 && $offset % 2 === 0) {
                            $consumibles = ['CINTA-AISL', 'TEFLON', 'BRIDA', 'DISCO-CORTE', 'DISCO-DESB', 'CEPILLO', 'BROCA', 'LIJA', 'PAÑO', 'BOLSA'];
                            $lineas[] = $this->line($consumibles[intdiv($offset, 2) % count($consumibles)], 1);
                        }
                        $this->delivery($trabajador->id, $lineas, $day, 'Solicitud diaria de EPP consumible para tareas en planta');
                    }
                    if ($day->day === 10) {
                        $this->clock($day, 14);
                        $monthly[$day->format('Y-m')] = $this->prestamo($this->trabajadores[((int) $month) % 20]->id, [$this->unidades['TALADRO'][0], $this->unidades['TALADRO'][1]], $day, $day->addDays(7)->toDateString(), 'Herramientas para intervención programada');
                    }
                    if (in_array($day->day, [12, 16], true) && isset($monthly[$day->format('Y-m')])) {
                        // Leave the final month's second item outstanding for daily monitoring.
                        if (! ($day->day === 16 && $day->format('Y-m') === $end->format('Y-m'))) {
                            $this->clock($day, 15);
                            $this->returnItems($monthly[$day->format('Y-m')], [$day->day === 12 ? 0 : 1], $day, 'Recepción parcial de herramientas; verificación física simulada');
                        }
                    }
                    if ($offset === 180) {
                        $this->clock($day, 16);
                        $this->returnItems($permanent[0], [0], $day, 'Devolución parcial de kit por cambio de tarea');
                    }
                    if ($day->isLastOfMonth()) {
                        $progress?->__invoke($day->format('Y-m'));
                    }
                }
                $this->clock($end, 17);
                $this->inventory('receipt', [$this->line('GUANTE-NIT', 100)], $end, 'Pendiente de recepción y conteo físico', false);
                $this->delivery($this->trabajadores[0]->id, [$this->line('GUANTE-NIT', 1)], $end, 'Solicitud pendiente de entrega física', false);
                $this->prestamo($this->trabajadores[3]->id, [$this->unidades['TALADRO'][2]], $end, null, 'Solicitud pendiente de préstamo', false);

                $summary = $this->verify();

                return ['marker' => self::MARKER, 'from' => $this->start->toDateString(), 'through' => $end->toDateString(), 'summary' => $summary, 'credentials' => $this->credentials];
            }, attempts: 1);
        } finally {
            Date::setTestNow($oldClock);
        }
    }

    private function clock(CarbonImmutable $day, int $hour): void
    {
        Date::setTestNow($day->setTime($hour, 0)->utc());
    }

    private function people(Usuario $administrator): void
    {
        $names = [['Carlos', 'Mendoza Rojas'], ['José Luis', 'Vargas Flores'], ['Miguel', 'Quiroga Salazar'], ['Juan', 'Roca Pérez'], ['Luis', 'Camacho Gutiérrez'], ['Óscar', 'Paredes Suárez'], ['Diego', 'Arce Molina'], ['Fernando', 'Soto Aguilar'], ['Jorge', 'Medina Chávez'], ['Rubén', 'Torres Paz'], ['Daniel', 'Álvarez Romero'], ['Sergio', 'Castro Vaca'], ['Marco', 'Guzmán Velasco'], ['Edwin', 'Ríos Céspedes'], ['Álvaro', 'Peña Ortiz'], ['Víctor', 'Salinas Cruz'], ['Pedro', 'Morales Arias'], ['Héctor', 'Fuentes López'], ['Raúl', 'Navarro Soria'], ['Andrés', 'León Fernández'], ['Marcos', 'Rivera Sandoval'], ['Patricia', 'Moreno Valdez'], ['Gabriela', 'Rojas Mercado'], ['Roberto', 'Silva Vargas']];
        $staff = ['Responsable de almacén', 'Referente técnico', 'Supervisora de seguridad y salud', 'Coordinador administrativo'];
        foreach ($names as $i => [$first, $last]) {
            $position = $i < 6 ? 'Mecánico de mantenimiento' : ($i < 10 ? 'Electricista industrial' : ($i < 14 ? 'Instrumentista' : ($i < 18 ? 'Técnico de mantenimiento' : 'Soldador')));
            $this->trabajadores[] = app(GuardarTrabajador::class)->handle($administrator, null, [
                'codigo' => sprintf('RG-%s-%03d', $i < 20 ? 'OP' : 'ST', $i < 20 ? $i + 1 : $i - 19),
                'nombres' => $first, 'apellidos' => $last, 'documento_identidad' => null,
                'cargo' => $i < 20 ? $position : $staff[$i - 20], 'area' => $i < 20 ? 'Operaciones y mantenimiento' : 'Staff del contrato', 'activo' => true,
            ]);
        }
        foreach (['almacen', 'referente', 'seguridad', 'administracion'] as $i => $account) {
            $rol = Rol::where('codigo', $i < 2 ? Rol::WAREHOUSE_MANAGER : Rol::AUDITOR)->firstOrFail();
            $contrasena = 'Rg!'.bin2hex(random_bytes(10)).'A7';
            $trabajador = $this->trabajadores[20 + $i];
            $usuario = app(CrearUsuario::class)->handle($administrator, ['nombre' => $trabajador->nombres.' '.$trabajador->apellidos, 'correo' => $account.'@demo.sga.test', 'contrasena' => $contrasena, 'rol_id' => $rol->id, 'activo' => true]);
            $this->credentials[] = ['correo' => $usuario->correo, 'contrasena' => $contrasena, 'rol' => $rol->nombre];
            if ($i === 0) {
                $this->operator = $usuario;
            }
        }
    }

    private function catalog(Usuario $administrator): void
    {
        $unidad = app(GuardarUnidadMedida::class)->handle($administrator, null, ['codigo' => 'UNID', 'nombre' => 'Unidad', 'simbolo' => 'unid.', 'dimension' => 'count', 'decimales' => 0, 'activo' => true]);
        $pair = app(GuardarUnidadMedida::class)->handle($administrator, null, ['codigo' => 'PAR', 'nombre' => 'Par', 'simbolo' => 'par', 'dimension' => 'count', 'decimales' => 0, 'activo' => true]);
        $root = app(GuardarUbicacion::class)->handle($administrator, null, ['codigo' => 'ALM-RG', 'nombre' => 'Almacén del contrato Río Grande', 'tipo' => 'sector', 'padre_id' => null, 'permite_almacenar' => false, 'descripcion' => self::MARKER, 'activo' => true]);
        foreach (['ropa' => 'Dotación de ropa y calzado', 'epp' => 'EPP de consumo diario', 'tool' => 'Herramientas e instrumentos'] as $key => $nombre) {
            $this->ubicaciones[$key] = app(GuardarUbicacion::class)->handle($administrator, null, ['codigo' => 'RG-'.strtoupper($key).'-01', 'nombre' => $nombre, 'tipo' => $key === 'tool' ? 'cabinet' : 'shelf', 'padre_id' => $root->id, 'permite_almacenar' => true, 'descripcion' => self::MARKER, 'activo' => true])->id;
        }
        $categorias = [];
        foreach (['ROPA' => 'Ropa de trabajo', 'CALZADO' => 'Calzado de seguridad', 'EPP' => 'Protección personal y consumibles', 'MANUAL' => 'Herramientas manuales', 'MEDICION' => 'Instrumentos de medición', 'ELECTRICA' => 'Herramientas eléctricas'] as $key => $nombre) {
            $categorias[$key] = app(GuardarCategoria::class)->handle($administrator, null, ['codigo' => 'RG-'.$key, 'nombre' => $nombre, 'descripcion' => self::MARKER, 'activo' => true]);
        }
        $size = app(GuardarAtributoCatalogo::class)->handle($administrator, null, ['codigo' => 'RG-TALLA', 'nombre' => 'Talla', 'tipo_dato' => 'text', 'unidad_medida_id' => null, 'texto_ayuda' => 'Talla comercial del artículo. '.self::MARKER, 'activo' => true]);
        $tipo = app(GuardarAtributoCatalogo::class)->handle($administrator, null, ['codigo' => 'RG-PRENDA', 'nombre' => 'Tipo de prenda', 'tipo_dato' => 'text', 'unidad_medida_id' => null, 'texto_ayuda' => self::MARKER, 'activo' => true]);
        $definitions = [];
        foreach (['ROPA', 'CALZADO'] as $categoria) {
            $definitions[$categoria]['size'] = app(GuardarAtributoCategoria::class)->handle($administrator, $categorias[$categoria], null, ['atributo_id' => $size->id, 'obligatorio' => true, 'identificacion_tecnica' => true, 'orden_presentacion' => 1, 'activo' => true])->id;
        }
        $definitions['ROPA']['tipo'] = app(GuardarAtributoCategoria::class)->handle($administrator, $categorias['ROPA'], null, ['atributo_id' => $tipo->id, 'obligatorio' => true, 'identificacion_tecnica' => true, 'orden_presentacion' => 2, 'activo' => true])->id;
        $entries = [];
        foreach (['S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $sizeLabel) {
            foreach (['OVEROL' => 'Overol de trabajo', 'CAMISA' => 'Camisa de trabajo manga larga', 'PANTALON' => 'Pantalón de trabajo'] as $key => $nombre) {
                $entries[$key.'-'.$sizeLabel] = [$nombre.' talla '.$sizeLabel, 'ROPA', 'ropa', $unidad->id, 40, [$definitions['ROPA']['size'] => $sizeLabel, $definitions['ROPA']['tipo'] => $key], false];
            }
        }
        foreach (range(36, 46) as $shoe) {
            $entries['BOTIN-'.$shoe] = ['Botín de seguridad talla '.$shoe, 'CALZADO', 'ropa', $pair->id, 25, [$definitions['CALZADO']['size'] => (string) $shoe], false];
        }
        foreach (['CASCO' => ['Casco de seguridad', 100, false], 'GUANTE-NIT' => ['Guantes recubiertos de nitrilo', 300, true], 'GUANTE-VAQ' => ['Guantes de vaqueta', 200, true], 'RESP-PART' => ['Respirador para partículas', 250, false], 'FILTRO' => ['Filtros de repuesto para respirador', 100, true], 'TAPON' => ['Tapones auditivos', 250, true], 'LENTE' => ['Lentes de seguridad transparentes', 150, false]] as $key => [$nombre, $qty, $isPair]) {
            $entries[$key] = [$nombre, 'EPP', 'epp', $isPair ? $pair->id : $unidad->id, $qty, [], false];
        }
        foreach (['LLAVE' => ['Llave ajustable 12 pulgadas', 'MANUAL'], 'COMBINADAS' => ['Juego de llaves combinadas', 'MANUAL'], 'ALICATE' => ['Alicate universal', 'MANUAL'], 'DESTORNILLADOR' => ['Juego de destornilladores', 'MANUAL'], 'MULTIMETRO' => ['Multímetro digital', 'MEDICION'], 'CALIBRADOR' => ['Calibrador de procesos', 'MEDICION'], 'AMOLADORA' => ['Amoladora angular', 'ELECTRICA'], 'TALADRO' => ['Taladro portátil', 'ELECTRICA']] as $key => [$nombre, $categoria]) {
            $entries[$key] = [$nombre, $categoria, 'tool', $unidad->id, 8, [], true];
        }
        foreach (['CHALECO' => 'Chaleco reflectivo', 'IMPERMEABLE' => 'Traje impermeable', 'CHAQUETA' => 'Chaqueta de trabajo', 'CAMISETA' => 'Camiseta de trabajo'] as $key => $nombre) {
            foreach (['S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $talla) {
                $entries[$key.'-'.$talla] = [$nombre.' talla '.$talla, 'ROPA', 'ropa', $unidad->id, 20, [$definitions['ROPA']['size'] => $talla, $definitions['ROPA']['tipo'] => $key], false];
            }
        }
        foreach (['OREJERA' => 'Protector auditivo tipo copa', 'LENTE-OSCURO' => 'Lentes de seguridad oscuros', 'ANTIPARRA' => 'Antiparras de seguridad', 'FACIAL' => 'Protector facial', 'BARBIQUEJO' => 'Barbiquejo para casco', 'RESP-MEDIA' => 'Respirador de media cara', 'CARTUCHO' => 'Cartucho de repuesto para respirador', 'GUANTE-SOLD' => 'Guantes para soldador', 'GUANTE-CORTE' => 'Guantes de protección mecánica', 'GUANTE-DIEL' => 'Guantes dieléctricos', 'DELANTAL' => 'Delantal de cuero para soldador', 'MANGA' => 'Mangas de protección', 'RODILLERA' => 'Rodilleras de trabajo', 'CAPUCHA' => 'Capucha para soldadura', 'CARETA' => 'Careta para soldadura', 'ARNES' => 'Arnés de cuerpo completo', 'ESLINGA' => 'Eslinga de seguridad', 'CONO' => 'Cono de señalización', 'CINTA-PELIGRO' => 'Rollo de cinta de señalización', 'CANDADO' => 'Candado para bloqueo', 'TARJETA' => 'Tarjeta de bloqueo', 'BLOQUEO' => 'Dispositivo de bloqueo', 'PAÑO' => 'Paños absorbentes', 'BOLSA' => 'Bolsas para residuos', 'CINTA-AISL' => 'Rollo de cinta aislante', 'TEFLON' => 'Rollo de cinta de sellado', 'BRIDA' => 'Paquete de bridas plásticas', 'DISCO-CORTE' => 'Disco de corte', 'DISCO-DESB' => 'Disco de desbaste', 'CEPILLO' => 'Cepillo de alambre', 'BROCA' => 'Juego de brocas', 'LIJA' => 'Pliego de lija'] as $key => $nombre) {
            $entries[$key] = [$nombre, 'EPP', 'epp', $unidad->id, 60, [], false];
        }
        foreach (['TORQUIMETRO' => ['Torquímetro', 'MEDICION'], 'PINZA-AMP' => ['Pinza amperimétrica', 'MEDICION'], 'MEGOHMETRO' => ['Medidor de aislamiento', 'MEDICION'], 'PIE-REY' => ['Calibrador pie de rey', 'MEDICION'], 'MICROMETRO' => ['Micrómetro', 'MEDICION'], 'MANOMETRO' => ['Manómetro de prueba', 'MEDICION'], 'DETECTOR' => ['Detector portátil de gases', 'MEDICION'], 'TERMOMETRO' => ['Termómetro infrarrojo', 'MEDICION'], 'EXTRACTOR' => ['Extractor de rodamientos', 'MANUAL'], 'DADOS' => ['Juego de dados', 'MANUAL'], 'MARTILLO' => ['Martillo de mecánico', 'MANUAL'], 'LLAVE-TUBO' => ['Llave para tubos', 'MANUAL'], 'REMACHADORA' => ['Remachadora manual', 'MANUAL'], 'PRENSA' => ['Prensa de banco portátil', 'MANUAL'], 'SIERRA' => ['Sierra eléctrica portátil', 'ELECTRICA'], 'ATORNILLADOR' => ['Atornillador a batería', 'ELECTRICA']] as $key => [$nombre, $categoria]) {
            $entries[$key] = [$nombre, $categoria, 'tool', $unidad->id, 8, [], true];
        }
        foreach ($entries as $key => [$nombre, $categoria, $ubicacion, $measurement, $opening, $values, $individual]) {
            $producto = app(GuardarProducto::class)->handle($administrator, null, ['codigo_interno' => 'RG-'.$key, 'nombre' => $nombre, 'descripcion' => self::MARKER.' Catálogo ficticio del contrato; no acredita especificaciones ni certificaciones reales.', 'categoria_id' => $categorias[$categoria]->id, 'unidad_medida_id' => $measurement, 'marca' => null, 'referencia_fabricante' => null, 'modo_control' => $individual ? 'individual' : 'quantity', 'prestable' => $individual, 'activo' => true, 'values' => $values]);
            app(GuardarUbicacionProducto::class)->handle($administrator, $producto, null, ['ubicacion_id' => $this->ubicaciones[$ubicacion], 'activo' => true, 'version_bloqueo' => $producto->version_bloqueo]);
            $ids = [];
            if ($individual) {
                foreach (range(1, 8) as $index) {
                    $ids[] = app(GuardarUnidadIndividual::class)->handle($administrator, null, ['identificador' => sprintf('RG-%s-%03d', $key, $index), 'producto_id' => $producto->id, 'numero_serie' => null, 'ubicacion_id' => null, 'observaciones' => self::MARKER, 'activo' => true])->id;
                }
                $this->unidades[$key] = $ids;
            }
            $this->productos[$key] = ['id' => $producto->id, 'ubicacion' => $this->ubicaciones[$ubicacion], 'opening' => $opening, 'unidades' => $ids];
        }
    }

    private function line(string $key, int $cantidad, array $unidades = []): array
    {
        $entry = $this->productos[$key];

        return ['producto_id' => $entry['id'], 'ubicacion_id' => $entry['ubicacion'], 'cantidad' => (string) $cantidad, 'unit_ids' => $unidades];
    }

    private function inventory(string $clase, array $lineas, CarbonImmutable $day, string $observaciones, bool $confirm = true): void
    {
        $actor = $clase === 'opening' ? $this->administrator : $this->operator;
        $doc = app(GuardarDocumentoInventario::class)->handle($actor, null, ['tipo' => $clase, 'fecha_documento' => $day->toDateString(), 'fecha_corte' => $clase === 'opening' ? $day->toDateString() : null, 'identificacion_contraparte' => $clase === 'opening' ? null : 'PROV-DEMO-RG', 'nombre_contraparte' => $clase === 'opening' ? null : 'Suministros Industriales del Oriente (simulado)', 'referencia_externa' => $clase === 'opening' ? null : 'DEMO-'.$day->format('Ym').($confirm ? '-R' : '-B'), 'observaciones' => self::MARKER.' '.$observaciones, 'lineas' => $lineas, 'expected_line_count' => count($lineas)]);
        if ($confirm) {
            app(ConfirmarDocumentoInventario::class)->handle($actor, $doc, 1);
        }
    }

    private function delivery(int $trabajador, array $lineas, CarbonImmutable $day, string $observaciones, bool $confirm = true): void
    {
        $doc = app(GuardarEntrega::class)->handle($this->operator, null, ['trabajador_id' => $trabajador, 'fecha_documento' => $day->toDateString(), 'observaciones' => self::MARKER.' '.$observaciones, 'lineas' => $lineas, 'expected_line_count' => count($lineas)]);
        if ($confirm) {
            app(ConfirmarEntrega::class)->handle($this->operator, $doc, 1);
        }
    }

    private function prestamo(int $trabajador, array $unidades, CarbonImmutable $day, ?string $due, string $observaciones, bool $confirm = true): PrestamoHerramienta
    {
        $lineas = array_map(fn ($id) => ['unidad_individual_id' => $id, 'ubicacion_id' => $this->ubicaciones['tool']], $unidades);
        $prestamo = app(GuardarPrestamo::class)->handle($this->operator, null, ['trabajador_id' => $trabajador, 'fecha_documento' => $day->toDateString(), 'fecha_limite' => $due, 'observaciones' => self::MARKER.' '.$observaciones, 'lineas' => $lineas, 'expected_line_count' => count($lineas)]);

        return $confirm ? app(ConfirmarPrestamo::class)->handle($this->operator, $prestamo, 1) : $prestamo;
    }

    private function returnItems(PrestamoHerramienta $prestamo, array $indexes, CarbonImmutable $day, string $observaciones): void
    {
        $items = $prestamo->lineas()->get();
        $lineas = array_map(fn ($index) => ['item_prestamo_id' => $items[$index]->id, 'ubicacion_id' => $this->ubicaciones['tool']], $indexes);
        $return = app(GuardarDevolucion::class)->handle($this->operator, null, ['prestamo_id' => $prestamo->id, 'fecha_documento' => $day->toDateString(), 'observaciones' => self::MARKER.' '.$observaciones, 'lineas' => $lineas, 'expected_line_count' => count($lineas)]);
        app(ConfirmarDevolucion::class)->handle($this->operator, $return, 1);
    }

    public function verify(): array
    {
        $mismatch = DB::selectOne("SELECT COUNT(*) AS n FROM existencias_ubicacion s LEFT JOIN (SELECT producto_id, ubicacion_id, SUM(CASE WHEN direccion='in' THEN cantidad ELSE -cantidad END) AS balance FROM movimientos_inventario GROUP BY producto_id,ubicacion_id) m ON m.producto_id=s.producto_id AND m.ubicacion_id=s.ubicacion_id WHERE s.cantidad < 0 OR s.cantidad <> COALESCE(m.balance,0)");
        $presence = DB::selectOne('SELECT COUNT(*) AS n FROM custodias_unidad_individual c JOIN posiciones_unidad_individual p ON p.unidad_individual_id=c.unidad_individual_id WHERE c.item_prestamo_actual_id IS NOT NULL AND p.presente=1');
        if ((int) $mismatch->n !== 0 || (int) $presence->n !== 0) {
            throw new RuntimeException('La conciliación de saldos o custodias no coincide; se revierte la carga completa.');
        }
        $counts = [];
        foreach (['trabajadores', 'usuarios', 'categorias', 'productos', 'unidades_individuales', 'documentos_inventario', 'entregas_materiales', 'prestamos_herramientas', 'devoluciones_herramientas', 'movimientos_inventario', 'existencias_ubicacion'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $counts['current_custodies'] = DB::table('custodias_unidad_individual')->whereNotNull('item_prestamo_actual_id')->count();
        $counts['stock_mismatches'] = (int) $mismatch->n;
        $counts['custody_presence_conflicts'] = (int) $presence->n;

        return $counts;
    }
}
