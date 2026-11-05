@extends('layouts.app')
@section('title', 'Existencias')
@section('content')
<h1>Existencias por producto y ubicación</h1>
<p>Solo documentos confirmados. Una identidad de catálogo sin recepción física no representa existencias. Las combinaciones sin saldo registrado equivalen a cero.</p>
<div class="actions">@can('movements.view')<a href="{{ route('movements.index') }}">Historial de movimientos</a>@endcan @can('availability.view')<a href="{{ route('availability.index') }}">Disponibilidad para operar</a>@endcan</div>
@include('inventario.query-filters')
<section class="card"><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Ubicación</th><th>Cantidad</th></tr></thead><tbody>@forelse($positions as $position)<tr><td>{{ $position->producto->codigo_interno }} · {{ $position->producto->nombre }}</td><td>{{ $position->ubicacion->codigo }}</td><td>{{ $position->cantidad }} {{ $position->producto->unidadMedida->simbolo }}</td></tr>@empty<tr><td colspan="3">Sin saldos registrados para estos filtros.</td></tr>@endforelse</tbody></table></div>{{ $positions->links() }}</section>
<h2>Presencia física de unidades individualizadas</h2>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Identificador</th><th>Producto</th><th>Presencia</th><th>Ubicación actual</th><th>Última operación</th></tr></thead><tbody>@forelse($individuals as $unidad)<tr><td>{{ $unidad->identificador }}</td><td>{{ $unidad->codigo_interno }}</td><td>{{ $unidad->presente ? 'En almacén' : 'Fuera del almacén' }}</td><td>{{ $unidad->location_code ?? 'Sin ubicación física actual' }}</td><td>{{ $unidad->ultima_linea_documento_id ? 'Remito/apertura #'.$unidad->ultima_linea_documento_id : (($unidad->ultima_linea_entrega_id ?? null) ? 'Entrega #'.$unidad->ultima_linea_entrega_id : (($unidad->ultimo_item_prestamo_id ?? null) ? 'Préstamo #'.$unidad->ultimo_item_prestamo_id : 'Devolución #'.($unidad->ultimo_item_devolucion_id ?? ''))) }}</td></tr>@empty<tr><td colspan="5">Sin unidades con movimientos registrados.</td></tr>@endforelse</tbody></table></div>{{ $individuals->links() }}</section>
@endsection
