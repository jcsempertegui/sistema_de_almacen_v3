@extends('layouts.app')
@section('title', 'Borrador de inventario')
@section('content')
<h1>{{ $documento->exists ? 'Editar '.$documento->codigo : 'Nuevo documento' }} · {{ \App\Models\DocumentoInventario::KINDS[$documento->tipo] }}</h1>
<p>Guardar un borrador no cambia existencias. Las ubicaciones deben estar asociadas activamente a cada producto.</p>
@if($documento->tipo === 'opening')<div class="notice">La apertura solo se confirma una vez por producto y antes de cualquier movimiento. Incluye todas sus ubicaciones en el mismo lote.</div>@endif
<form id="inventory-form" method="POST" action="{{ $documento->exists ? route('documentos-inventario.update', $documento) : route('documentos-inventario.store') }}">
@csrf @if($documento->exists) @method('PUT') @endif
<input type="hidden" name="tipo" value="{{ $documento->tipo }}"><input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}">
<section class="card"><div class="form-grid">
<div class="field"><label for="fecha_documento">Fecha del documento</label><input type="date" id="fecha_documento" name="fecha_documento" value="{{ old('fecha_documento', $documento->fecha_documento?->format('Y-m-d')) }}" required></div>
@if($documento->tipo === 'opening')<div class="field"><label for="fecha_corte">Fecha de corte del lote</label><input type="date" id="fecha_corte" name="fecha_corte" value="{{ old('fecha_corte', $documento->fecha_corte?->format('Y-m-d')) }}" required></div>
@else
<div class="field"><label for="identificacion_contraparte">Identificación de contraparte</label><input id="identificacion_contraparte" name="identificacion_contraparte" value="{{ old('identificacion_contraparte', $documento->identificacion_contraparte) }}" maxlength="80" required></div>
<div class="field"><label for="nombre_contraparte">Nombre de contraparte</label><input id="nombre_contraparte" name="nombre_contraparte" value="{{ old('nombre_contraparte', $documento->nombre_contraparte) }}" maxlength="200" required></div>
<div class="field"><label for="referencia_externa">Referencia externa (opcional)</label><input id="referencia_externa" name="referencia_externa" value="{{ old('referencia_externa', $documento->referencia_externa) }}" maxlength="100"><span class="help">Las coincidencias se advertirán para revisión; no se bloquean automáticamente.</span></div>
@endif
<div class="field"><label for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones" maxlength="10000">{{ old('observaciones', $documento->observaciones) }}</textarea></div>
</div></section>
@php
    $rawRows = old('lineas', $documento->lineas->map(fn ($row) => ['producto_id' => $row->producto_id, 'ubicacion_id' => $row->ubicacion_id, 'cantidad' => $row->cantidad, 'unit_ids' => $row->unidades->pluck('id')->all()])->all());
    $rows = is_array($rawRows) && $rawRows ? array_values($rawRows) : [[]];
    $rows = array_map(fn ($row) => is_array($row) ? $row : [], $rows);
@endphp
<div id="inventory-lineas">@foreach($rows as $i => $line)@include('inventario.documents.line')@endforeach</div>
<button type="button" id="add-line" class="secondary">Añadir línea</button>
<input id="expected_line_count" type="hidden" name="expected_line_count" value="{{ count($rows) }}">
<div class="actions"><button>Guardar borrador</button><a href="{{ route('documentos-inventario.index') }}">Volver</a></div>
</form>
<template id="line-template">@include('inventario.documents.line', ['i' => '__INDEX__', 'line' => []])</template>
<script>
(() => {
    const list = document.getElementById('inventory-lineas');
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
