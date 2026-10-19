@extends('layouts.app')
@section('title', 'Borrador de inventario')
@section('content')
<h1>{{ $document->exists ? 'Editar '.$document->code : 'Nuevo documento' }} · {{ \App\Models\InventoryDocument::KINDS[$document->kind] }}</h1>
<p>Guardar un borrador no cambia existencias. Las ubicaciones deben estar asociadas activamente a cada producto.</p>
@if($document->kind === 'opening')<div class="notice">La apertura solo se confirma una vez por producto y antes de cualquier movimiento. Incluye todas sus ubicaciones en el mismo lote.</div>@endif
<form id="inventory-form" method="POST" action="{{ $document->exists ? route('inventory-documents.update', $document) : route('inventory-documents.store') }}">
@csrf @if($document->exists) @method('PUT') @endif
<input type="hidden" name="kind" value="{{ $document->kind }}"><input type="hidden" name="lock_version" value="{{ $document->lock_version }}">
<section class="card"><div class="form-grid">
<div class="field"><label for="document_date">Fecha del documento</label><input type="date" id="document_date" name="document_date" value="{{ old('document_date', $document->document_date?->format('Y-m-d')) }}" required></div>
@if($document->kind === 'opening')<div class="field"><label for="cutoff_date">Fecha de corte del lote</label><input type="date" id="cutoff_date" name="cutoff_date" value="{{ old('cutoff_date', $document->cutoff_date?->format('Y-m-d')) }}" required></div>
@else
<div class="field"><label for="counterparty_id">Identificación de contraparte</label><input id="counterparty_id" name="counterparty_id" value="{{ old('counterparty_id', $document->counterparty_id) }}" maxlength="80" required></div>
<div class="field"><label for="counterparty_name">Nombre de contraparte</label><input id="counterparty_name" name="counterparty_name" value="{{ old('counterparty_name', $document->counterparty_name) }}" maxlength="200" required></div>
<div class="field"><label for="external_reference">Referencia externa (opcional)</label><input id="external_reference" name="external_reference" value="{{ old('external_reference', $document->external_reference) }}" maxlength="100"><span class="help">Las coincidencias se advertirán para revisión; no se bloquean automáticamente.</span></div>
@endif
<div class="field"><label for="notes">Observaciones</label><textarea id="notes" name="notes" maxlength="10000">{{ old('notes', $document->notes) }}</textarea></div>
</div></section>
@php
    $rawRows = old('lines', $document->lines->map(fn ($row) => ['product_id' => $row->product_id, 'location_id' => $row->location_id, 'quantity' => $row->quantity, 'unit_ids' => $row->units->pluck('id')->all()])->all());
    $rows = is_array($rawRows) && $rawRows ? array_values($rawRows) : [[]];
    $rows = array_map(fn ($row) => is_array($row) ? $row : [], $rows);
@endphp
<div id="inventory-lines">@foreach($rows as $i => $line)@include('inventory.documents.line')@endforeach</div>
<button type="button" id="add-line" class="secondary">Añadir línea</button>
<input id="expected_line_count" type="hidden" name="expected_line_count" value="{{ count($rows) }}">
<div class="actions"><button>Guardar borrador</button><a href="{{ route('inventory-documents.index') }}">Volver</a></div>
</form>
<template id="line-template">@include('inventory.documents.line', ['i' => '__INDEX__', 'line' => []])</template>
<script>
(() => {
    const list = document.getElementById('inventory-lines');
    let next = {{ count($rows) }};
    const count = () => document.getElementById('expected_line_count').value = list.children.length;
    document.getElementById('add-line').addEventListener('click', () => {
        if (list.children.length >= 100) { alert('Para formularios utiliza hasta 100 líneas. Divide los remitos o usa CSV para aperturas mayores.'); return; }
        list.insertAdjacentHTML('beforeend', document.getElementById('line-template').innerHTML.replaceAll('__INDEX__', String(next++)));
        count(); list.lastElementChild.querySelector('select').focus();
    });
    list.addEventListener('click', event => {
        if (event.target.matches('.remove-line') && list.children.length > 1) { event.target.closest('fieldset').remove(); count(); }
    });
    document.getElementById('inventory-form').addEventListener('submit', count);
})();
</script>
@endsection
