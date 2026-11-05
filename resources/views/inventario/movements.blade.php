@extends('layouts.app')
@section('title', 'Movimientos')
@section('content')
<h1>Historial de movimientos</h1><p>Orden de registro físico. La fecha comercial del documento se conserva por separado. Los movimientos confirmados no se editan ni eliminan.</p>
@include('inventario.query-filters')
<section class="card"><div class="table-wrap"><table><thead><tr><th>Registro (hora de Bolivia)</th><th>Documento / línea</th><th>Producto</th><th>Ubicación</th><th>Sentido</th><th>Cantidad</th><th>Identificadores</th><th>Operador</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->fecha_registro->timezone(\App\Models\DocumentoInventario::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s') }}</td><td>@php($origen = $movement->origen) @php($doc = $origen->documento)
@if($movement->origin_kind === 'inventory-documents')
@can('view', $doc)<a href="{{ route('documentos-inventario.show',$doc) }}">{{ $doc->codigo }}</a>@else{{ $doc->codigo }}@endcan
@elseif($movement->origin_kind === 'corrections')
@can('view', $doc)<a href="{{ route('corrections.show',[$doc->tipo,$doc->id]) }}">{{ $doc->codigo }}</a>@else{{ $doc->codigo }}@endcan
@else
@can('view', $doc)<a href="{{ route('operaciones-trabajadores.show',[$movement->origin_kind,$doc->id]) }}">{{ $doc->codigo }}</a>@else{{ $doc->codigo }}@endcan
@endif / {{ $origen->numero_linea ?? $origen->id }}</td><td>{{ $movement->producto->codigo_interno }}</td><td>{{ $movement->ubicacion->codigo }}</td><td>{{ $movement->direccion === 'in' ? 'Ingreso' : 'Egreso' }}</td><td>{{ $movement->cantidad }} {{ $origen->simbolo_unidad ?? $origen->itemPrestamo?->simbolo_unidad ?? '' }}</td><td>{{ $movement->identifiers ?: 'Por cantidad' }}</td><td>#{{ $movement->creado_por }}</td></tr>@empty<tr><td colspan="8">Sin movimientos para estos filtros.</td></tr>@endforelse</tbody></table></div>{{ $movements->links() }}</section>
@endsection
