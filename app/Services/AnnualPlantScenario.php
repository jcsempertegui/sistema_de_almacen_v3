<?php

namespace App\Services;

use App\Actions\Catalog\SaveCatalogAttribute;
use App\Actions\Catalog\SaveCategory;
use App\Actions\Catalog\SaveCategoryAttribute;
use App\Actions\Catalog\SaveIndividualUnit;
use App\Actions\Catalog\SaveLocation;
use App\Actions\Catalog\SaveMeasurementUnit;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductLocation;
use App\Actions\Deliveries\ConfirmDelivery;
use App\Actions\Deliveries\SaveDelivery;
use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Inventory\SaveInventoryDocument;
use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Actions\Users\CreateUser;
use App\Actions\Workers\SaveWorker;
use App\Models\Role;
use App\Models\ToolLoan;
use App\Models\User;
use App\Support\TestingDatabaseGuard;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit, one-time fictional history. Never registered as a general seeder. */
class AnnualPlantScenario
{
    public const MARKER = '[SIMULACIÓN ANUAL RG]';

    private array $products = [];

    private array $workers = [];

    private array $units = [];

    private array $locations = [];

    private array $credentials = [];

    private User $operator;

    private User $administrator;

    private CarbonImmutable $start;

    public function run(User $administrator, CarbonImmutable $end, array $expectedIdentity, ?Closure $progress = null, bool $allowPrimary = false): array
    {
        $identity = TestingDatabaseGuard::identity(DB::connection()->getPdo());
        if ($identity !== $expectedIdentity || ! in_array($identity['database_name'], ['sga_rio_grande', TestingDatabaseGuard::DATABASE], true)
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
        $this->products = $this->workers = $this->units = $this->locations = $this->credentials = [];
        try {
            return DB::transaction(function () use ($administrator, $end, $progress) {
                app(SecurityMutation::class)->requirePermissions($administrator, 'users.create', 'users.assign_role', 'products.create', 'initial_stock.confirm');
                // Shared mutex also prevents two parallel loaders from passing the empty-state checks.
                DB::table('permissions')->where('code', 'roles.assign_permissions')->lockForUpdate()->firstOrFail();
                foreach (['workers', 'categories', 'measurement_units', 'locations', 'products', 'inventory_documents', 'inventory_movements', 'material_deliveries', 'tool_loans', 'tool_returns'] as $table) {
                    if (DB::table($table)->exists()) {
                        throw new RuntimeException('La carga requiere catálogo y operaciones vacíos; se conserva todo lo existente: '.$table);
                    }
                }
                $this->clock($this->start, 6);
                $this->people($administrator);
                $this->catalog($administrator);
                $this->clock($this->start, 7);
                $opening = [];
                foreach ($this->products as $key => $entry) {
                    $opening[] = $this->line($key, $entry['opening'], $entry['units']);
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
                        foreach ($this->workers as $i => $worker) {
                            $size = ['M', 'L', 'XL', 'XXL'][$i % 4];
                            $shoe = (string) (39 + $i % 4);
                            $this->delivery($worker->id, [
                                $this->line('OVEROL-'.$size, 1), $this->line('CAMISA-'.$size, 2),
                                $this->line('PANTALON-'.$size, 2), $this->line('BOTIN-'.$shoe, 1),
                                $this->line('CASCO', 1), $this->line('LENTE', 1),
                            ], $day, 'Dotación semestral de EPP; talla '.$size.', calzado '.$shoe);
                        }
                    }
                    if ($offset === 1) {
                        $this->clock($day, 10);
                        foreach (array_slice($this->workers, 0, 20) as $i => $worker) {
                            $types = $i < 6 ? ['LLAVE', 'COMBINADAS'] : ($i < 10 ? ['MULTIMETRO', 'ALICATE'] : ($i < 14 ? ['CALIBRADOR', 'MULTIMETRO'] : ($i < 18 ? ['DESTORNILLADOR', 'ALICATE'] : ['AMOLADORA', 'LLAVE'])));
                            $ids = array_map(fn ($type) => array_shift($this->units[$type]), $types);
                            $permanent[] = $this->loan($worker->id, $ids, $day, $i < 4 ? $end->subDays(16)->toDateString() : null, 'Asignación de herramientas para actividades del contrato');
                        }
                    }
                    $this->clock($day, 12);
                    for ($request = 0; $request < 1 + $offset % 3; $request++) {
                        $worker = $this->workers[($offset * 3 + $request) % 20];
                        $glove = ($offset + $request) % 3 === 0 ? 'GUANTE-VAQ' : 'GUANTE-NIT';
                        $extra = ['RESP-PART', 'TAPON', 'FILTRO', 'LENTE'][($offset + $request) % 4];
                        $this->delivery($worker->id, [$this->line($glove, 1), $this->line($extra, 1)], $day, 'Solicitud diaria de EPP consumible para tareas en planta');
                    }
                    if ($day->day === 10) {
                        $this->clock($day, 14);
                        $monthly[$day->format('Y-m')] = $this->loan($this->workers[((int) $month) % 20]->id, [$this->units['TALADRO'][0], $this->units['TALADRO'][1]], $day, $day->addDays(7)->toDateString(), 'Herramientas para intervención programada');
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
                $this->delivery($this->workers[0]->id, [$this->line('GUANTE-NIT', 1)], $end, 'Solicitud pendiente de entrega física', false);
                $this->loan($this->workers[3]->id, [$this->units['TALADRO'][2]], $end, null, 'Solicitud pendiente de préstamo', false);

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

    private function people(User $administrator): void
    {
        $names = [['Carlos', 'Mendoza Rojas'], ['José Luis', 'Vargas Flores'], ['Miguel', 'Quiroga Salazar'], ['Juan', 'Roca Pérez'], ['Luis', 'Camacho Gutiérrez'], ['Óscar', 'Paredes Suárez'], ['Diego', 'Arce Molina'], ['Fernando', 'Soto Aguilar'], ['Jorge', 'Medina Chávez'], ['Rubén', 'Torres Paz'], ['Daniel', 'Álvarez Romero'], ['Sergio', 'Castro Vaca'], ['Marco', 'Guzmán Velasco'], ['Edwin', 'Ríos Céspedes'], ['Álvaro', 'Peña Ortiz'], ['Víctor', 'Salinas Cruz'], ['Pedro', 'Morales Arias'], ['Héctor', 'Fuentes López'], ['Raúl', 'Navarro Soria'], ['Andrés', 'León Fernández'], ['Marcos', 'Rivera Sandoval'], ['Patricia', 'Moreno Valdez'], ['Gabriela', 'Rojas Mercado'], ['Roberto', 'Silva Vargas']];
        $staff = ['Responsable de almacén', 'Referente técnico', 'Supervisora de seguridad y salud', 'Coordinador administrativo'];
        foreach ($names as $i => [$first, $last]) {
            $position = $i < 6 ? 'Mecánico de mantenimiento' : ($i < 10 ? 'Electricista industrial' : ($i < 14 ? 'Instrumentista' : ($i < 18 ? 'Técnico de mantenimiento' : 'Soldador')));
            $this->workers[] = app(SaveWorker::class)->handle($administrator, null, [
                'codigo' => sprintf('RG-%s-%03d', $i < 20 ? 'OP' : 'ST', $i < 20 ? $i + 1 : $i - 19),
                'nombres' => $first, 'apellidos' => $last, 'documento_identidad' => null,
                'cargo' => $i < 20 ? $position : $staff[$i - 20], 'area' => $i < 20 ? 'Operaciones y mantenimiento' : 'Staff del contrato', 'is_active' => true,
            ]);
        }
        foreach (['almacen', 'referente', 'seguridad', 'administracion'] as $i => $account) {
            $role = Role::where('code', $i < 2 ? Role::WAREHOUSE_MANAGER : Role::AUDITOR)->firstOrFail();
            $password = 'Rg!'.bin2hex(random_bytes(10)).'A7';
            $worker = $this->workers[20 + $i];
            $user = app(CreateUser::class)->handle($administrator, ['name' => $worker->nombres.' '.$worker->apellidos, 'email' => $account.'@demo.sga.test', 'password' => $password, 'role_id' => $role->id, 'is_active' => true]);
            $this->credentials[] = ['email' => $user->email, 'password' => $password, 'role' => $role->name];
            if ($i === 0) {
                $this->operator = $user;
            }
        }
    }

    private function catalog(User $administrator): void
    {
        $unit = app(SaveMeasurementUnit::class)->handle($administrator, null, ['code' => 'UNID', 'name' => 'Unidad', 'symbol' => 'unid.', 'dimension' => 'count', 'decimal_places' => 0, 'is_active' => true]);
        $pair = app(SaveMeasurementUnit::class)->handle($administrator, null, ['code' => 'PAR', 'name' => 'Par', 'symbol' => 'par', 'dimension' => 'count', 'decimal_places' => 0, 'is_active' => true]);
        $root = app(SaveLocation::class)->handle($administrator, null, ['code' => 'ALM-RG', 'name' => 'Almacén del contrato Río Grande', 'type' => 'sector', 'parent_id' => null, 'is_storable' => false, 'description' => self::MARKER, 'is_active' => true]);
        foreach (['ropa' => 'Dotación de ropa y calzado', 'epp' => 'EPP de consumo diario', 'tool' => 'Herramientas e instrumentos'] as $key => $name) {
            $this->locations[$key] = app(SaveLocation::class)->handle($administrator, null, ['code' => 'RG-'.strtoupper($key).'-01', 'name' => $name, 'type' => $key === 'tool' ? 'cabinet' : 'shelf', 'parent_id' => $root->id, 'is_storable' => true, 'description' => self::MARKER, 'is_active' => true])->id;
        }
        $categories = [];
        foreach (['ROPA' => 'Ropa de trabajo', 'CALZADO' => 'Calzado de seguridad', 'EPP' => 'Protección personal y consumibles', 'MANUAL' => 'Herramientas manuales', 'MEDICION' => 'Instrumentos de medición', 'ELECTRICA' => 'Herramientas eléctricas'] as $key => $name) {
            $categories[$key] = app(SaveCategory::class)->handle($administrator, null, ['code' => 'RG-'.$key, 'name' => $name, 'description' => self::MARKER, 'is_active' => true]);
        }
        $size = app(SaveCatalogAttribute::class)->handle($administrator, null, ['code' => 'RG-TALLA', 'name' => 'Talla', 'data_type' => 'text', 'measurement_unit_id' => null, 'help_text' => 'Talla comercial del artículo. '.self::MARKER, 'is_active' => true]);
        $type = app(SaveCatalogAttribute::class)->handle($administrator, null, ['code' => 'RG-PRENDA', 'name' => 'Tipo de prenda', 'data_type' => 'text', 'measurement_unit_id' => null, 'help_text' => self::MARKER, 'is_active' => true]);
        $definitions = [];
        foreach (['ROPA', 'CALZADO'] as $category) {
            $definitions[$category]['size'] = app(SaveCategoryAttribute::class)->handle($administrator, $categories[$category], null, ['attribute_id' => $size->id, 'is_required' => true, 'is_identity' => true, 'display_order' => 1, 'is_active' => true])->id;
        }
        $definitions['ROPA']['type'] = app(SaveCategoryAttribute::class)->handle($administrator, $categories['ROPA'], null, ['attribute_id' => $type->id, 'is_required' => true, 'is_identity' => true, 'display_order' => 2, 'is_active' => true])->id;
        $entries = [];
        foreach (['M', 'L', 'XL', 'XXL'] as $sizeLabel) {
            foreach (['OVEROL' => 'Overol de trabajo', 'CAMISA' => 'Camisa de trabajo manga larga', 'PANTALON' => 'Pantalón de trabajo'] as $key => $name) {
                $entries[$key.'-'.$sizeLabel] = [$name.' talla '.$sizeLabel, 'ROPA', 'ropa', $unit->id, 40, [$definitions['ROPA']['size'] => $sizeLabel, $definitions['ROPA']['type'] => $key], false];
            }
        }
        foreach (range(39, 42) as $shoe) {
            $entries['BOTIN-'.$shoe] = ['Botín de seguridad talla '.$shoe, 'CALZADO', 'ropa', $pair->id, 25, [$definitions['CALZADO']['size'] => (string) $shoe], false];
        }
        foreach (['CASCO' => ['Casco de seguridad', 100, false], 'GUANTE-NIT' => ['Guantes recubiertos de nitrilo', 300, true], 'GUANTE-VAQ' => ['Guantes de vaqueta', 200, true], 'RESP-PART' => ['Respirador para partículas', 250, false], 'FILTRO' => ['Filtros de repuesto para respirador', 100, true], 'TAPON' => ['Tapones auditivos', 250, true], 'LENTE' => ['Lentes de seguridad transparentes', 150, false]] as $key => [$name, $qty, $isPair]) {
            $entries[$key] = [$name, 'EPP', 'epp', $isPair ? $pair->id : $unit->id, $qty, [], false];
        }
        foreach (['LLAVE' => ['Llave ajustable 12 pulgadas', 'MANUAL'], 'COMBINADAS' => ['Juego de llaves combinadas', 'MANUAL'], 'ALICATE' => ['Alicate universal', 'MANUAL'], 'DESTORNILLADOR' => ['Juego de destornilladores', 'MANUAL'], 'MULTIMETRO' => ['Multímetro digital', 'MEDICION'], 'CALIBRADOR' => ['Calibrador de procesos', 'MEDICION'], 'AMOLADORA' => ['Amoladora angular', 'ELECTRICA'], 'TALADRO' => ['Taladro portátil', 'ELECTRICA']] as $key => [$name, $category]) {
            $entries[$key] = [$name, $category, 'tool', $unit->id, 8, [], true];
        }
        foreach ($entries as $key => [$name, $category, $location, $measurement, $opening, $values, $individual]) {
            $product = app(SaveProduct::class)->handle($administrator, null, ['internal_code' => 'RG-'.$key, 'name' => $name, 'description' => self::MARKER.' Catálogo ficticio del contrato; no acredita especificaciones ni certificaciones reales.', 'category_id' => $categories[$category]->id, 'measurement_unit_id' => $measurement, 'brand' => null, 'manufacturer_reference' => null, 'control_mode' => $individual ? 'individual' : 'quantity', 'is_loanable' => $individual, 'is_active' => true, 'values' => $values]);
            app(SaveProductLocation::class)->handle($administrator, $product, null, ['location_id' => $this->locations[$location], 'is_active' => true, 'lock_version' => $product->lock_version]);
            $ids = [];
            if ($individual) {
                foreach (range(1, 8) as $index) {
                    $ids[] = app(SaveIndividualUnit::class)->handle($administrator, null, ['identifier' => sprintf('RG-%s-%03d', $key, $index), 'product_id' => $product->id, 'serial_number' => null, 'location_id' => null, 'notes' => self::MARKER, 'is_active' => true])->id;
                }
                $this->units[$key] = $ids;
            }
            $this->products[$key] = ['id' => $product->id, 'location' => $this->locations[$location], 'opening' => $opening, 'units' => $ids];
        }
    }

    private function line(string $key, int $quantity, array $units = []): array
    {
        $entry = $this->products[$key];

        return ['product_id' => $entry['id'], 'location_id' => $entry['location'], 'quantity' => (string) $quantity, 'unit_ids' => $units];
    }

    private function inventory(string $kind, array $lines, CarbonImmutable $day, string $notes, bool $confirm = true): void
    {
        $actor = $kind === 'opening' ? $this->administrator : $this->operator;
        $doc = app(SaveInventoryDocument::class)->handle($actor, null, ['kind' => $kind, 'document_date' => $day->toDateString(), 'cutoff_date' => $kind === 'opening' ? $day->toDateString() : null, 'counterparty_id' => $kind === 'opening' ? null : 'PROV-DEMO-RG', 'counterparty_name' => $kind === 'opening' ? null : 'Suministros Industriales del Oriente (simulado)', 'external_reference' => $kind === 'opening' ? null : 'DEMO-'.$day->format('Ym').($confirm ? '-R' : '-B'), 'notes' => self::MARKER.' '.$notes, 'lines' => $lines, 'expected_line_count' => count($lines)]);
        if ($confirm) {
            app(ConfirmInventoryDocument::class)->handle($actor, $doc, 1);
        }
    }

    private function delivery(int $worker, array $lines, CarbonImmutable $day, string $notes, bool $confirm = true): void
    {
        $doc = app(SaveDelivery::class)->handle($this->operator, null, ['worker_id' => $worker, 'document_date' => $day->toDateString(), 'notes' => self::MARKER.' '.$notes, 'lines' => $lines, 'expected_line_count' => count($lines)]);
        if ($confirm) {
            app(ConfirmDelivery::class)->handle($this->operator, $doc, 1);
        }
    }

    private function loan(int $worker, array $units, CarbonImmutable $day, ?string $due, string $notes, bool $confirm = true): ToolLoan
    {
        $lines = array_map(fn ($id) => ['individual_unit_id' => $id, 'location_id' => $this->locations['tool']], $units);
        $loan = app(SaveLoan::class)->handle($this->operator, null, ['worker_id' => $worker, 'document_date' => $day->toDateString(), 'due_date' => $due, 'notes' => self::MARKER.' '.$notes, 'lines' => $lines, 'expected_line_count' => count($lines)]);

        return $confirm ? app(ConfirmLoan::class)->handle($this->operator, $loan, 1) : $loan;
    }

    private function returnItems(ToolLoan $loan, array $indexes, CarbonImmutable $day, string $notes): void
    {
        $items = $loan->lines()->get();
        $lines = array_map(fn ($index) => ['loan_item_id' => $items[$index]->id, 'location_id' => $this->locations['tool']], $indexes);
        $return = app(SaveReturn::class)->handle($this->operator, null, ['loan_id' => $loan->id, 'document_date' => $day->toDateString(), 'notes' => self::MARKER.' '.$notes, 'lines' => $lines, 'expected_line_count' => count($lines)]);
        app(ConfirmReturn::class)->handle($this->operator, $return, 1);
    }

    public function verify(): array
    {
        $mismatch = DB::selectOne("SELECT COUNT(*) AS n FROM stock_positions s LEFT JOIN (SELECT product_id, location_id, SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END) AS balance FROM inventory_movements GROUP BY product_id,location_id) m ON m.product_id=s.product_id AND m.location_id=s.location_id WHERE s.quantity < 0 OR s.quantity <> COALESCE(m.balance,0)");
        $presence = DB::selectOne('SELECT COUNT(*) AS n FROM individual_unit_custodies c JOIN individual_unit_positions p ON p.individual_unit_id=c.individual_unit_id WHERE c.current_loan_item_id IS NOT NULL AND p.is_present=1');
        if ((int) $mismatch->n !== 0 || (int) $presence->n !== 0) {
            throw new RuntimeException('La conciliación de saldos o custodias no coincide; se revierte la carga completa.');
        }
        $counts = [];
        foreach (['workers', 'users', 'categories', 'products', 'individual_units', 'inventory_documents', 'material_deliveries', 'tool_loans', 'tool_returns', 'inventory_movements', 'stock_positions'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $counts['current_custodies'] = DB::table('individual_unit_custodies')->whereNotNull('current_loan_item_id')->count();
        $counts['stock_mismatches'] = (int) $mismatch->n;
        $counts['custody_presence_conflicts'] = (int) $presence->n;

        return $counts;
    }
}
