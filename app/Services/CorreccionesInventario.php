<?php

namespace App\Services;

use App\Models\CorreccionInventario;
use App\Models\DevolucionHerramienta;
use App\Models\DocumentoInventario;
use App\Models\EntregaMaterial;
use App\Models\ItemPrestamoHerramienta;
use App\Models\MovimientoInventario;
use App\Models\PrestamoHerramienta;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Support\DecimalTecnico;
use App\Support\FirmaHistorica;
use App\Support\ValidacionOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorreccionesInventario
{
    public const SOURCES = [
        'inventory-documents' => [DocumentoInventario::class, 'linea_documento_id', 'documento_inventario_id'],
        'deliveries' => [EntregaMaterial::class, 'linea_entrega_id', 'entrega_id'],
        'loans' => [PrestamoHerramienta::class, 'item_prestamo_id', 'prestamo_id'],
        'returns' => [DevolucionHerramienta::class, 'item_devolucion_id', 'devolucion_id'],
        'corrections' => [CorreccionInventario::class, 'linea_correccion_id', 'correccion_destino_id'],
    ];

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['lineas' => $message]);
    }

    private function permiso(Usuario $actor, string $clase, string $accion): void
    {
        app(ModificacionSegura::class)->exigirPermisos($actor, ($clase === 'adjustment' ? 'adjustments' : 'compensations').'.'.$accion);
    }

    private function locked(int $id): CorreccionInventario
    {
        return CorreccionInventario::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function event(Usuario $u, int $authorization, string $accion, array $detalle = []): void
    {
        DB::table('eventos_autorizacion_correccion')->insert(['autorizacion_id' => $authorization, 'actor_id' => $u->id, 'accion' => $accion, 'detalle' => json_encode($detalle, JSON_THROW_ON_ERROR), 'fecha_creacion' => now()]);
    }

    public function sourceMovements(string $clase, int $id)
    {
        abort_unless(isset(self::SOURCES[$clase]), 422, 'Tipo documental inválido.');
        [$class,$column] = self::SOURCES[$clase];
        $doc = $class::whereKey($id)->lockForUpdate()->firstOrFail();
        if (($clase === 'corrections' && ($doc->estado !== 'executed' || $doc->tipo !== 'adjustment')) || ($clase !== 'corrections' && $doc->estado !== 'confirmed')) {
            $this->fail('Selecciona un documento confirmado; no se compensan compensaciones.');
        }
        if (DB::table('compensaciones_documento')->where(self::SOURCES[$clase][2], $id)->lockForUpdate()->first()) {
            $this->fail('El documento ya fue compensado.');
        }

        return MovimientoInventario::whereIn($column, $doc->lineas()->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    public function movementUnits(MovimientoInventario $m): array
    {
        if ($m->item_prestamo_id !== null || $m->item_devolucion_id !== null) {
            return [(int) $m->origen->unidad_individual_id];
        }

        return $m->origen->unidades()->orderBy('unidades_individuales.id')->pluck('unidades_individuales.id')->map(fn ($id) => (int) $id)->all();
    }

    private function stock(int $producto, int $ubicacion): object
    {
        return DB::table('existencias_ubicacion')->where('producto_id', $producto)->where('ubicacion_id', $ubicacion)->lockForUpdate()->first() ?? (object) ['cantidad' => '0.000000', 'version_bloqueo' => 0];
    }

    public function save(Usuario $actor, ?CorreccionInventario $target, array $input): CorreccionInventario
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target, $input) {
            ValidacionOperacion::make($input, ['tipo' => 'required|in:adjustment,compensation', 'motivo' => 'required|string|max:1000', 'fecha_documento' => 'required|date_format:Y-m-d'])->validate();
            $this->permiso($u, $input['tipo'], $target ? 'update' : 'create');
            $doc = $target ? $this->locked($target->id) : null;
            if ($doc) {
                abort_unless(! in_array($doc->estado, ['executed', 'cancelled']) && $doc->tipo === $input['tipo'] && (string) ($input['version_bloqueo'] ?? '') === (string) $doc->version_bloqueo, 409, 'La corrección cambió o está cerrada.');
            }
            $lineas = [];
            $seen = [];
            if ($input['tipo'] === 'adjustment') {
                ValidacionOperacion::make($input, ['lineas' => 'required|array|min:1|max:100', 'lineas.*.producto_id' => 'required|integer|min:1', 'lineas.*.ubicacion_id' => 'required|integer|min:1', 'lineas.*.cantidad_contada' => 'required', 'lineas.*.unit_ids' => 'present|array|max:1000', 'lineas.*.unit_ids.*' => 'required|integer|min:1|distinct', 'expected_line_count' => 'required|integer|min:1|max:100'])->validate();
                if ((int) $input['expected_line_count'] !== count($input['lineas'])) {
                    $this->fail('El formulario está incompleto.');
                }$pairs = [];
                foreach ($input['lineas'] as $i => $raw) {
                    $pair = $raw['producto_id'].':'.$raw['ubicacion_id'];
                    if (isset($pairs[$pair])) {
                        $this->fail('No repitas producto y ubicación.');
                    }$pairs[$pair] = true;
                    $p = Producto::whereKey($raw['producto_id'])->lockForUpdate()->firstOrFail();
                    $measure = UnidadMedida::findOrFail($p->unidad_medida_id);
                    $count = DecimalTecnico::normalize($raw['cantidad_contada'], 'lineas.'.$i.'.cantidad_contada', $measure->decimales);
                    if ($count === null || DecimalTecnico::compare($count, '0') < 0) {
                        $this->fail('El conteo no puede ser negativo.');
                    }$stock = $this->stock($p->id, (int) $raw['ubicacion_id']);
                    $difference = DB::selectOne('SELECT CAST(? AS DECIMAL(18,6))-CAST(? AS DECIMAL(18,6)) AS n', [$count, $stock->cantidad])->n;
                    if (DecimalTecnico::compare($difference, '0') === 0) {
                        $this->fail('El conteo coincide con el saldo: no corresponde un movimiento de ajuste.');
                    }
                    $line = app(ValidadorBorradorInventario::class)->line(array_replace($raw, ['cantidad' => ltrim($difference, '-')]), 'lineas.'.$i, $seen);
                    $lineas[] = $line + ['direccion' => str_starts_with($difference, '-') ? 'out' : 'in', 'cantidad_observada' => $stock->cantidad, 'version_observada' => $stock->version_bloqueo, 'cantidad_contada' => $count];
                }
            } else {
                ValidacionOperacion::make($input, ['tipo_origen' => 'required|in:inventory-documents,deliveries,loans,returns,corrections', 'origen_id' => 'required|integer|min:1'])->validate();
                $sourcePermission = $input['tipo_origen'] === 'inventory-documents' ? DocumentoInventario::findOrFail($input['origen_id'])->permiso('view') : ['deliveries' => 'deliveries.view', 'loans' => 'loans.view', 'returns' => 'returns.view', 'corrections' => 'adjustments.view'][$input['tipo_origen']];
                app(ModificacionSegura::class)->exigirPermisos($u, $sourcePermission);
                $movements = $this->sourceMovements($input['tipo_origen'], (int) $input['origen_id']);
                if ($movements->isEmpty()) {
                    $this->fail('El documento no tiene movimientos compensables.');
                }
                foreach ($movements as $m) {
                    $origen = $m->item_devolucion_id !== null ? ItemPrestamoHerramienta::findOrFail($m->origen->item_prestamo_id) : $m->origen;
                    $stock = $this->stock($m->producto_id, $m->ubicacion_id);
                    $lineas[] = ['producto_id' => $m->producto_id, 'ubicacion_id' => $m->ubicacion_id, 'cantidad' => $m->cantidad, 'direccion' => $m->direccion === 'in' ? 'out' : 'in', 'unidad_medida_id' => $origen->unidad_medida_id, 'simbolo_unidad' => $origen->simbolo_unidad, 'decimales' => $origen->decimales ?? 0, 'modo_control' => $origen->modo_control ?? 'individual', 'cantidad_observada' => $stock->cantidad, 'version_observada' => $stock->version_bloqueo, 'cantidad_contada' => null, 'movimiento_original_id' => $m->id, 'direccion_original' => $m->direccion, 'unit_ids' => $this->movementUnits($m)];
                }
            }
            $values = ['tipo' => $input['tipo'], 'motivo' => trim($input['motivo']), 'fecha_documento' => $input['fecha_documento'], 'tipo_origen' => $input['tipo'] === 'compensation' ? $input['tipo_origen'] : null, 'origen_id' => $input['tipo'] === 'compensation' ? (int) $input['origen_id'] : null, 'estado' => 'draft', 'modificado_por' => $u->id, 'fecha_actualizacion' => now()];
            if ($doc) {
                $this->invalidate($u, $doc->id);
                DB::table('correcciones_inventario')->where('id', $doc->id)->update($values + ['version_bloqueo' => $doc->version_bloqueo + 1]);
                $ids = $doc->lineas()->pluck('id');
                DB::table('unidades_linea_correccion')->whereIn('linea_correccion_id', $ids)->delete();
                DB::table('lineas_correccion_inventario')->whereIn('id', $ids)->delete();
                $id = $doc->id;
            } else {
                $id = DB::table('correcciones_inventario')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'creado_por' => $u->id, 'fecha_creacion' => now()]);
            }
            foreach ($lineas as $line) {
                $unidades = $line['unit_ids'];
                unset($line['unit_ids']);
                $lineId = DB::table('lineas_correccion_inventario')->insertGetId($line + ['correccion_id' => $id, 'tipo' => $input['tipo']]);
                foreach ($unidades as $unidad) {
                    DB::table('unidades_linea_correccion')->insert(['linea_correccion_id' => $lineId, 'unidad_individual_id' => $unidad, 'producto_id' => $line['producto_id']]);
                }
            }
            $doc = CorreccionInventario::findOrFail($id);
            $this->huella($doc);
            app(AuditoriaCritica::class)->record($u, 'correccion.saved', 'correccion_id', $id, ['version' => $doc->version_bloqueo]);

            return $doc;
        }, protectAdministrativeAccess: false);
    }

    private function huella(CorreccionInventario $doc): string
    {
        $state = ['document' => FirmaHistorica::campos('inventory_corrections', $doc->only(['id', 'tipo', 'version_bloqueo', 'motivo', 'fecha_documento', 'tipo_origen', 'origen_id'])), 'lines' => []];
        if ($doc->tipo === 'compensation') {
            $expected = $this->sourceMovements($doc->tipo_origen, $doc->origen_id)->pluck('id')->all();
            $actual = $doc->lineas()->orderBy('movimiento_original_id')->pluck('movimiento_original_id')->all();
            sort($expected);
            sort($actual);
            if ($actual !== $expected) {
                $this->fail('La compensación debe incluir todos los movimientos del documento.');
            }
        }
        foreach ($doc->lineas()->lockForUpdate()->get() as $line) {
            $p = Producto::whereKey($line->producto_id)->lockForUpdate()->firstOrFail();
            $loc = Ubicacion::whereKey($line->ubicacion_id)->lockForUpdate()->firstOrFail();
            $unidad = UnidadMedida::whereKey($line->unidad_medida_id)->lockForUpdate()->firstOrFail();
            $pair = DB::table('ubicaciones_producto')->where('producto_id', $p->id)->where('ubicacion_id', $loc->id)->lockForUpdate()->first();
            if (! $p->activo || ! $loc->activo || ! $loc->permite_almacenar || ! $unidad->activo || ! $pair?->activo || $p->unidad_medida_id !== $unidad->id || $p->modo_control !== $line->modo_control || $unidad->simbolo !== $line->simbolo_unidad || $unidad->decimales !== $line->decimales) {
                $this->fail('Regulariza expresamente el catálogo antes de corregir; las definiciones históricas no se reinterpretan.');
            }
            app(JerarquiaUbicaciones::class)->validateParent($loc, true);
            $stock = $this->stock($p->id, $loc->id);
            if ($doc->tipo === 'adjustment' && ((int) $stock->version_bloqueo !== (int) $line->version_observada || DecimalTecnico::compare($stock->cantidad, $line->cantidad_observada) !== 0)) {
                $this->fail('El saldo cambió después del conteo. Edita y revisa nuevamente.');
            }
            $unidades = $line->unidades()->orderBy('unidades_individuales.id')->lockForUpdate()->get();
            if (($line->modo_control === 'individual' && DecimalTecnico::compare($line->cantidad, (string) $unidades->count()) !== 0) || ($line->modo_control === 'quantity' && $unidades->isNotEmpty())) {
                $this->fail('Las identidades no corresponden a la cantidad.');
            }
            $identityState = [];
            foreach ($unidades as $identity) {
                if (! $identity->activo || $identity->producto_id !== $p->id) {
                    $this->fail('La identidad debe estar activa y pertenecer al producto.');
                }$position = DB::table('posiciones_unidad_individual')->where('unidad_individual_id', $identity->id)->lockForUpdate()->first();
                $custody = DB::table('custodias_unidad_individual')->where('unidad_individual_id', $identity->id)->lockForUpdate()->first();
                $original = $line->movimiento_original_id ? MovimientoInventario::findOrFail($line->movimiento_original_id) : null;
                if ($original?->item_prestamo_id !== null) {
                    $cycle = app(CiclosPrestamo::class)->current($identity->id, $original->item_prestamo_id);
                    if ($cycle->item_prestamo_apertura_id === null) {
                        $this->fail('El préstamo tiene ciclos posteriores: no se compensan dependencias automáticamente.');
                    }
                } elseif ($custody?->item_prestamo_actual_id !== null) {
                    $this->fail('La herramienta tiene un préstamo vigente. No puede ajustarse ni compensarse esta operación.');
                }
                if (($line->direccion === 'in' && $position?->presente) || ($line->direccion === 'out' && (! $position?->presente || (int) $position->ubicacion_id !== $loc->id))) {
                    $this->fail('La presencia física no permite esta corrección.');
                }
                if ($original) {
                    $origen = $original->linea_documento_id !== null ? 'ultima_linea_documento_id' : ($original->linea_entrega_id !== null ? 'ultima_linea_entrega_id' : ($original->item_prestamo_id !== null ? 'ultimo_item_prestamo_id' : ($original->item_devolucion_id !== null ? 'ultimo_item_devolucion_id' : 'ultima_linea_correccion_id')));
                    $originId = $original->linea_documento_id ?? $original->linea_entrega_id ?? $original->item_prestamo_id ?? $original->item_devolucion_id ?? $original->linea_correccion_id;
                    if ((int) ($position?->{$origen} ?? 0) !== (int) $originId) {
                        $this->fail('La identidad tiene operaciones posteriores; revisa primero sus dependencias.');
                    }
                }
                $identityState[] = ['identity' => FirmaHistorica::campos('individual_units', $identity->getAttributes()), 'position' => FirmaHistorica::campos('individual_unit_positions', $position), 'custody' => FirmaHistorica::campos('individual_unit_custodies', $custody)];
            }
            $state['lines'][] = ['line' => FirmaHistorica::campos('inventory_correction_lines', $line->getAttributes()), 'product' => FirmaHistorica::campos('products', $p->getAttributes()), 'location' => FirmaHistorica::campos('locations', $loc->getAttributes()), 'unit' => FirmaHistorica::campos('measurement_units', $unidad->getAttributes()), 'pair' => FirmaHistorica::campos('product_locations', $pair), 'stock' => FirmaHistorica::campos('stock_positions', $stock), 'identities' => $identityState];
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function invalidate(Usuario $u, int $id): void
    {
        foreach (DB::table('autorizaciones_correccion')->where('correccion_id', $id)->whereIn('estado', ['requested', 'approved'])->lockForUpdate()->get() as $a) {
            DB::table('autorizaciones_correccion')->where('id', $a->id)->update(['estado' => 'revoked']);
            $this->event($u, $a->id, 'revoked', ['motivo' => 'Contenido o estado cambiado.']);
        }
    }

    public function transition(Usuario $actor, CorreccionInventario $target, string $accion, mixed $version, ?string $motivo = null): CorreccionInventario
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target, $accion, $version, $motivo) {
            $doc = $this->locked($target->id);
            abort_unless((string) $version === (string) $doc->version_bloqueo, 409, 'La corrección cambió.');
            ValidacionOperacion::make(['accion' => $accion, 'motivo' => $motivo], ['accion' => 'required|in:request,approve,reject,revoke,cancel', 'motivo' => 'nullable|string|max:1000'])->validate();
            if ($accion === 'request') {
                $this->permiso($u, $doc->tipo, 'request');
                abort_unless(in_array($doc->estado, ['draft', 'rejected']), 409);
                $hash = $this->huella($doc);
                $a = DB::table('autorizaciones_correccion')->insertGetId(['correccion_id' => $doc->id, 'estado' => 'requested', 'huella' => $hash, 'version_documento' => $doc->version_bloqueo, 'solicitado_por' => $u->id, 'fecha_solicitud' => now()]);
                $this->event($u, $a, 'requested', ['huella' => $hash, 'documento' => $doc->toArray(), 'lineas' => $doc->lineas()->with('unidades')->get()->toArray()]);
                $estado = 'requested';
            } elseif ($accion === 'cancel') {
                $this->permiso($u, $doc->tipo, 'cancel');
                abort_unless(! in_array($doc->estado, ['executed', 'cancelled']), 409);
                $this->invalidate($u, $doc->id);
                $estado = 'cancelled';
            } else {
                app(ModificacionSegura::class)->exigirPermisos($u, $accion === 'revoke' ? 'corrections.revoke' : 'corrections.authorize');
                ValidacionOperacion::make(['motivo' => $motivo], ['motivo' => 'required|string|max:1000'])->validate();
                $a = DB::table('autorizaciones_correccion')->where('correccion_id', $doc->id)->orderByDesc('id')->lockForUpdate()->first();
                abort_unless($a && in_array($a->estado, ['requested', 'approved']), 409, 'No hay autorización pendiente.');
                if ($accion === 'approve') {
                    abort_unless($a->estado === 'requested', 409);
                    if ($a->solicitado_por === $u->id) {
                        $this->fail('El solicitante no puede autorizar su propia corrección.');
                    }if (! hash_equals($a->huella, $this->huella($doc))) {
                        $this->fail('El contenido o las referencias cambiaron; solicita nuevamente.');
                    }
                    DB::table('autorizaciones_correccion')->where('id', $a->id)->update(['estado' => 'approved', 'autorizado_por' => $u->id, 'motivo' => trim($motivo), 'fecha_autorizacion' => now(), 'fecha_vencimiento' => now()->addHours(24)]);
                    $estado = 'authorized';
                    $event = 'approved';
                } else {
                    $event = $accion === 'revoke' ? 'revoked' : 'rejected';
                    DB::table('autorizaciones_correccion')->where('id', $a->id)->update(['estado' => $event, 'motivo' => trim($motivo)]);
                    $estado = 'rejected';
                }
                $this->event($u, $a->id, $event, ['motivo' => $motivo, 'access_proof' => app(AccesoAutorizacion::class)->proof($u)]);
            }
            DB::table('correcciones_inventario')->where('id', $doc->id)->update(['estado' => $estado, 'modificado_por' => $u->id, 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'correccion.'.$accion, 'correccion_id', $doc->id, ['motivo' => $motivo]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }

    public function execute(Usuario $actor, CorreccionInventario $target, mixed $version): CorreccionInventario
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $u) use ($target, $version) {
            $doc = $this->locked($target->id);
            $this->permiso($u, $doc->tipo, 'execute');
            if ($doc->estado === 'executed') {
                return $doc;
            }
            abort_unless($doc->estado === 'authorized' && (string) $version === (string) $doc->version_bloqueo, 409, 'La corrección no está autorizada o cambió.');
            $auth = DB::table('autorizaciones_correccion')->where('correccion_id', $doc->id)->where('estado', 'approved')->orderByDesc('id')->lockForUpdate()->first();
            if (! $auth || $auth->fecha_vencimiento <= now()->toDateTimeString() || ! hash_equals($auth->huella, $this->huella($doc))) {
                $this->fail('La autorización caducó o cambió el contenido, catálogo o saldo.');
            }
            $proofEvent = DB::table('eventos_autorizacion_correccion')->where('autorizacion_id', $auth->id)->where('accion', 'approved')->orderByDesc('id')->lockForUpdate()->first();
            $proof = $proofEvent ? json_decode($proofEvent->detalle, true, flags: JSON_THROW_ON_ERROR) : [];
            if (! app(AccesoAutorizacion::class)->valid($auth->autorizado_por, 'corrections.authorize', $proof['access_proof'] ?? [])) {
                $this->fail('La capacidad del autorizador cambió. Solicita nueva autorización.');
            }
            foreach ($doc->lineas()->lockForUpdate()->get() as $line) {
                $this->post($u, $line);
            }
            if ($doc->tipo === 'compensation') {
                DB::table('compensaciones_documento')->insert(['correccion_id' => $doc->id, self::SOURCES[$doc->tipo_origen][2] => $doc->origen_id, 'fecha_creacion' => now()]);
            }
            DB::table('autorizaciones_correccion')->where('id', $auth->id)->update(['estado' => 'used', 'fecha_utilizacion' => now()]);
            $this->event($u, $auth->id, 'used');
            DB::table('correcciones_inventario')->where('id', $doc->id)->update(['estado' => 'executed', 'ejecutado_por' => $u->id, 'fecha_ejecucion' => now(), 'modificado_por' => $u->id, 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'correccion.executed', 'correccion_id', $doc->id, ['autorizacion_id' => $auth->id]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }

    private function post(Usuario $u, object $line): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Se requiere una transacción.');
        }
        $stock = $this->stock($line->producto_id, $line->ubicacion_id);
        if ((int) $stock->version_bloqueo === 0) {
            DB::table('existencias_ubicacion')->insert(['producto_id' => $line->producto_id, 'ubicacion_id' => $line->ubicacion_id, 'cantidad' => '0', 'version_bloqueo' => 1, 'fecha_creacion' => now(), 'fecha_actualizacion' => now()]);
        }
        $incoming = $line->direccion === 'in';
        $op = $incoming ? '+' : '-';
        $condition = $incoming ? 'cantidad <= CAST(999999999999.999999 AS DECIMAL(18,6))-CAST(? AS DECIMAL(18,6))' : 'cantidad >= CAST(? AS DECIMAL(18,6))';
        if (DB::update("UPDATE existencias_ubicacion SET cantidad=cantidad {$op} CAST(? AS DECIMAL(18,6)),version_bloqueo=version_bloqueo+1,fecha_actualizacion=? WHERE producto_id=? AND ubicacion_id=? AND {$condition}", [$line->cantidad, now(), $line->producto_id, $line->ubicacion_id, $line->cantidad]) !== 1) {
            $this->fail('La corrección excede el saldo disponible o la capacidad decimal.');
        }
        DB::table('movimientos_inventario')->insert(['linea_correccion_id' => $line->id, 'tipo_correccion' => $line->tipo, 'movimiento_compensado_id' => $line->movimiento_original_id, 'producto_id' => $line->producto_id, 'ubicacion_id' => $line->ubicacion_id, 'direccion' => $line->direccion, 'cantidad' => $line->cantidad, 'creado_por' => $u->id, 'fecha_registro' => now()]);
        foreach ($line->unidades()->orderBy('unidades_individuales.id')->get() as $unidad) {
            $position = DB::table('posiciones_unidad_individual')->where('unidad_individual_id', $unidad->id)->lockForUpdate()->first();
            DB::table('posiciones_unidad_individual')->updateOrInsert(['unidad_individual_id' => $unidad->id], ['producto_id' => $line->producto_id, 'presente' => $incoming, 'ubicacion_id' => $incoming ? $line->ubicacion_id : null, 'ultima_linea_documento_id' => null, 'ultima_linea_entrega_id' => null, 'ultimo_item_prestamo_id' => null, 'ultimo_item_devolucion_id' => null, 'ultima_linea_correccion_id' => $line->id, 'direccion_ultima_correccion' => $line->direccion, 'version_bloqueo' => ($position?->version_bloqueo ?? 0) + 1, 'fecha_creacion' => $position?->fecha_creacion ?? now(), 'fecha_actualizacion' => now()]);
            $original = $line->movimiento_original_id ? MovimientoInventario::findOrFail($line->movimiento_original_id) : null;
            if ($original?->item_prestamo_id !== null) {
                $cycle = app(CiclosPrestamo::class)->current($unidad->id, $original->item_prestamo_id);
                app(CiclosPrestamo::class)->close($cycle, null, $line->id);
                DB::table('custodias_unidad_individual')->where('unidad_individual_id', $unidad->id)->update(['ciclo_actual_id' => null, 'item_prestamo_actual_id' => null, 'version_bloqueo' => DB::raw('version_bloqueo+1'), 'fecha_actualizacion' => now()]);
            } elseif ($original?->item_devolucion_id !== null) {
                $closed = DB::table('cierres_ciclo_prestamo')->where('item_devolucion_id', $original->item_devolucion_id)->lockForUpdate()->first();
                if (! $closed) {
                    $this->fail('No existe el cierre físico original.');
                }$previous = DB::table('ciclos_custodia_prestamo')->find($closed->ciclo_id);
                $item = ItemPrestamoHerramienta::findOrFail($previous->item_prestamo_id);
                $cycle = app(CiclosPrestamo::class)->open($item, $line->id, $previous->id);
                $changed = DB::table('custodias_unidad_individual')->where('unidad_individual_id', $unidad->id)->whereNull('item_prestamo_actual_id')->update(['ciclo_actual_id' => $cycle, 'item_prestamo_actual_id' => $item->id, 'version_bloqueo' => DB::raw('version_bloqueo+1'), 'fecha_actualizacion' => now()]);
                if ($changed !== 1) {
                    $this->fail('La custodia cambió.');
                }
            }
        }
        Event::dispatch('inventory_correction.line_posted', [$line]);
    }
}
