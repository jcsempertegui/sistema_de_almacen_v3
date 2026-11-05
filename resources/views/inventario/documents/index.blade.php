@extends('layouts.app')
@section('title', 'Remitos')
@section('content')
<h1>Remitos</h1>
<p>Los borradores no modifican existencias. Confirmar registra movimientos definitivos una sola vez.</p>
<div class="actions">@can('remittances.create')<a href="{{ route('documentos-inventario.create', ['tipo' => 'receipt']) }}">Nuevo ingreso</a><a href="{{ route('documentos-inventario.create', ['tipo' => 'issue']) }}">Nuevo egreso</a>@endcan</div>
<section class="card"><form method="GET" class="form-grid">
<div class="field"><label for="q">Número interno, contraparte o referencia</label><input id="q" name="q" value="{{ request('q') }}" maxlength="100"></div>
<div class="field"><label for="tipo">Tipo</label><select id="tipo" name="tipo"><option value="">Todos</option>@foreach(\App\Models\DocumentoInventario::KINDS as $key => $label)<option value="{{ $key }}" @selected(request('tipo') === $key)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option>@foreach(\App\Models\DocumentoInventario::STATES as $key => $label)<option value="{{ $key }}" @selected(request('estado') === $key)>{{ $label }}</option>@endforeach</select></div><button>Buscar</button>
</form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Documento</th><th>Fecha</th><th>Contraparte</th><th>Referencia externa</th><th>Estado</th></tr></thead><tbody>
@forelse($documents as $documento)<tr><td><a href="{{ route('documentos-inventario.show', $documento) }}">{{ $documento->codigo }} · {{ \App\Models\DocumentoInventario::KINDS[$documento->tipo] }}</a></td><td>{{ $documento->fecha_documento->format('Y-m-d') }}</td><td>{{ $documento->nombre_contraparte ?? 'Apertura de existencias' }}</td><td>{{ $documento->referencia_externa ?? 'Sin referencia' }}</td><td>{{ \App\Models\DocumentoInventario::STATES[$documento->estado] }}</td></tr>@empty<tr><td colspan="5">No hay documentos para estos filtros.</td></tr>@endforelse
</tbody></table></div>{{ $documents->links() }}</section>
@endsection
