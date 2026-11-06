@extends('layouts.app')
@section('title', $label)
@section('content')
<h1>{{ $label }} · {{ $documento->exists ? 'Editar borrador' : 'Registrar borrador' }}</h1>
<p>Guardar no reserva ni modifica existencias. La confirmación representa la operación física efectiva.</p>
<form method="POST" action="{{ $documento->exists ? route('operaciones-trabajadores.update',[$clase,$documento->id]) : route('operaciones-trabajadores.store',$clase) }}" id="operation-form" class="card">
@csrf @if($documento->exists)@method('PUT')@endif
<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo ?? 1 }}">
<input type="hidden" name="expected_line_count" id="line-count" value="{{ count($rows) }}">
@if($clase==='returns')
<label>Préstamo original<select name="prestamo_id" id="prestamo-choice" required><option value="">Selecciona un préstamo</option>@foreach($loans as $prestamo)<option value="{{ $prestamo->id }}" @selected((string)\App\Support\FormularioOperacion::old('prestamo_id',$documento->prestamo_id)===(string)$prestamo->id)>{{ $prestamo->codigo }} · {{ $prestamo->datos_historicos_trabajador['nombres'] ?? $prestamo->trabajador->nombres }} {{ $prestamo->datos_historicos_trabajador['apellidos'] ?? $prestamo->trabajador->apellidos }}</option>@endforeach</select></label>
<p>El responsable se conserva desde el préstamo, aunque esté desactivado. El destino puede ser distinto del retiro.</p>
@else
<label>Trabajador responsable<select id="trabajador-choice" name="trabajador_id" required><option value="">Selecciona un trabajador</option>@foreach($trabajadores as $trabajador)<option value="{{ $trabajador->id }}" @selected((string)\App\Support\FormularioOperacion::old('trabajador_id',$documento->trabajador_id)===(string)$trabajador->id)>{{ $trabajador->codigo }} · {{ $trabajador->nombres }} {{ $trabajador->apellidos }}</option>@endforeach</select></label>
@endif
<label>Fecha de operación<input type="date" name="fecha_documento" required value="{{ \App\Support\FormularioOperacion::old('fecha_documento',$documento->fecha_documento?->format('Y-m-d') ?? now()->timezone('America/La_Paz')->format('Y-m-d')) }}"></label>
@if($clase==='loans')<label>Vencimiento opcional<input type="date" name="fecha_limite" value="{{ \App\Support\FormularioOperacion::old('fecha_limite',$documento->fecha_limite?->format('Y-m-d')) }}"></label>@endif
<label>Observaciones<textarea name="observaciones" maxlength="10000">{{ \App\Support\FormularioOperacion::old('observaciones',$documento->observaciones) }}</textarea></label>
<div id="operation-lineas">
@foreach($rows as $index=>$row)
    @include('operaciones-trabajadores.line', ['index'=>$index,'row'=>$row])
@endforeach
</div>
<button type="button" id="add-line" class="secondary">Añadir línea</button><button type="submit">Guardar borrador</button>
</form>
<template id="line-template">@include('operaciones-trabajadores.line', ['index'=>'__INDEX__','row'=>[]])</template>
<script>
(()=>{const form=document.getElementById('operation-form'),container=document.getElementById('operation-lineas'),prestamo=document.getElementById('prestamo-choice');let next={{ count($rows) }};
const update=()=>{document.getElementById('line-count').value=container.querySelectorAll('fieldset').length;container.querySelectorAll('[data-prestamo]').forEach(o=>{o.disabled=!!prestamo && o.dataset.prestamo!==prestamo.value;});};
document.getElementById('add-line').addEventListener('click',()=>{if(container.querySelectorAll('fieldset').length>=100)return;container.insertAdjacentHTML('beforeend',document.getElementById('line-template').innerHTML.replaceAll('__INDEX__',next++));update();});
container.addEventListener('click',e=>{if(e.target.matches('[data-remove]')){e.target.closest('fieldset').remove();update();}});if(prestamo)prestamo.addEventListener('change',()=>{container.querySelectorAll('[data-prestamo]').forEach(o=>{if(o.dataset.prestamo!==prestamo.value)o.selected=false;});update();});form.addEventListener('submit',update);update();})();
</script>
@endsection
