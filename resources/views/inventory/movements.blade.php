@extends('layouts.app')
@section('title', 'Movimientos')
@section('content')
<h1>Historial de movimientos</h1><p>Orden de registro físico. La fecha comercial del documento se conserva por separado. Los movimientos confirmados no se editan ni eliminan.</p>
@include('inventory.query-filters')
<section class="card"><div class="table-wrap"><table><thead><tr><th>Registro (hora de Bolivia)</th><th>Documento / línea</th><th>Producto</th><th>Ubicación</th><th>Sentido</th><th>Cantidad</th><th>Identificadores</th><th>Operador</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->posted_at->timezone(\App\Models\InventoryDocument::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s') }}</td><td>@php($origin = $movement->origin) @php($doc = $origin->document)
@if($movement->origin_kind === 'inventory-documents')
@can('view', $doc)<a href="{{ route('inventory-documents.show',$doc) }}">{{ $doc->code }}</a>@else{{ $doc->code }}@endcan
@else
@can('view', $doc)<a href="{{ route('worker-operations.show',[$movement->origin_kind,$doc->id]) }}">{{ $doc->code }}</a>@else{{ $doc->code }}@endcan
@endif / {{ $origin->line_number ?? $origin->id }}</td><td>{{ $movement->product->internal_code }}</td><td>{{ $movement->location->code }}</td><td>{{ $movement->direction === 'in' ? 'Ingreso' : 'Egreso' }}</td><td>{{ $movement->quantity }} {{ $origin->unit_symbol ?? $origin->loanItem?->unit_symbol ?? '' }}</td><td>{{ $movement->identifiers ?: 'Por cantidad' }}</td><td>#{{ $movement->created_by }}</td></tr>@empty<tr><td colspan="8">Sin movimientos para estos filtros.</td></tr>@endforelse</tbody></table></div>{{ $movements->links() }}</section>
@endsection
