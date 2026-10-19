@extends('layouts.app')
@section('title', 'Remitos y aperturas')
@section('content')
<h1>Remitos y aperturas</h1>
<p>Los borradores no modifican existencias. Confirmar registra movimientos definitivos una sola vez.</p>
<div class="actions">@can('remittances.create')<a href="{{ route('inventory-documents.create', ['kind' => 'receipt']) }}">Nuevo ingreso</a><a href="{{ route('inventory-documents.create', ['kind' => 'issue']) }}">Nuevo egreso</a>@endcan @can('initial_stock.create')<a href="{{ route('inventory-documents.create', ['kind' => 'opening']) }}">Apertura manual</a>@can('initial_stock.import')<a href="{{ route('initial-stock-import.create') }}">Importar apertura CSV</a>@endcan @endcan</div>
<section class="card"><form method="GET" class="form-grid">
<div class="field"><label for="q">Número interno, contraparte o referencia</label><input id="q" name="q" value="{{ request('q') }}" maxlength="100"></div>
<div class="field"><label for="kind">Tipo</label><select id="kind" name="kind"><option value="">Todos</option>@foreach(\App\Models\InventoryDocument::KINDS as $key => $label)<option value="{{ $key }}" @selected(request('kind') === $key)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="">Todos</option>@foreach(\App\Models\InventoryDocument::STATES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div><button>Buscar</button>
</form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Documento</th><th>Fecha</th><th>Contraparte</th><th>Referencia externa</th><th>Estado</th></tr></thead><tbody>
@forelse($documents as $document)<tr><td><a href="{{ route('inventory-documents.show', $document) }}">{{ $document->code }} · {{ \App\Models\InventoryDocument::KINDS[$document->kind] }}</a></td><td>{{ $document->document_date->format('Y-m-d') }}</td><td>{{ $document->counterparty_name ?? 'Apertura de existencias' }}</td><td>{{ $document->external_reference ?? 'Sin referencia' }}</td><td>{{ \App\Models\InventoryDocument::STATES[$document->status] }}</td></tr>@empty<tr><td colspan="5">No hay documentos para estos filtros.</td></tr>@endforelse
</tbody></table></div>{{ $documents->links() }}</section>
@endsection
