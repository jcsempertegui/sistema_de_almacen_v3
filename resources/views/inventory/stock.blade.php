@extends('layouts.app')
@section('title', 'Existencias')
@section('content')
<h1>Existencias por producto y ubicación</h1>
<p>Solo documentos confirmados. Una identidad de catálogo sin recepción física no representa existencias. Las combinaciones sin saldo registrado equivalen a cero.</p>
@include('inventory.query-filters')
<section class="card"><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Ubicación</th><th>Cantidad</th></tr></thead><tbody>@forelse($positions as $position)<tr><td>{{ $position->product->internal_code }} · {{ $position->product->name }}</td><td>{{ $position->location->code }}</td><td>{{ $position->quantity }} {{ $position->product->measurementUnit->symbol }}</td></tr>@empty<tr><td colspan="3">Sin saldos registrados para estos filtros.</td></tr>@endforelse</tbody></table></div>{{ $positions->links() }}</section>
<h2>Presencia física de unidades individualizadas</h2>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Identificador</th><th>Producto</th><th>Presencia</th><th>Ubicación actual</th><th>Última línea de documento</th></tr></thead><tbody>@forelse($individuals as $unit)<tr><td>{{ $unit->identifier }}</td><td>{{ $unit->internal_code }}</td><td>{{ $unit->is_present ? 'En almacén' : 'Fuera del almacén' }}</td><td>{{ $unit->location_code ?? 'Sin ubicación física actual' }}</td><td>#{{ $unit->last_document_line_id }}</td></tr>@empty<tr><td colspan="5">Sin unidades con movimientos registrados.</td></tr>@endforelse</tbody></table></div>{{ $individuals->links() }}</section>
@endsection
