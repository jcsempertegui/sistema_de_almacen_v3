@extends('layouts.app')
@section('title', $label)
@section('content')
<h1>{{ $label }} · {{ $document->exists ? 'Editar borrador' : 'Registrar borrador' }}</h1>
<p>Guardar no reserva ni modifica existencias. La confirmación representa la operación física efectiva.</p>
<form method="POST" action="{{ $document->exists ? route('worker-operations.update',[$kind,$document->id]) : route('worker-operations.store',$kind) }}" id="operation-form" class="card">
@csrf @if($document->exists)@method('PUT')@endif
<input type="hidden" name="lock_version" value="{{ $document->lock_version ?? 1 }}">
<input type="hidden" name="expected_line_count" id="line-count" value="{{ count($rows) }}">
@if($kind==='returns')
<label>Préstamo original<select name="loan_id" id="loan-choice" required><option value="">Selecciona un préstamo</option>@foreach($loans as $loan)<option value="{{ $loan->id }}" @selected((string)\App\Support\OperationForm::old('loan_id',$document->loan_id)===(string)$loan->id)>{{ $loan->code }} · {{ $loan->worker_snapshot['nombres'] ?? $loan->worker->nombres }} {{ $loan->worker_snapshot['apellidos'] ?? $loan->worker->apellidos }}</option>@endforeach</select></label>
<p>El responsable se conserva desde el préstamo, aunque esté desactivado. El destino puede ser distinto del retiro.</p>
@else
<label>Trabajador responsable<select name="worker_id" required><option value="">Selecciona un trabajador</option>@foreach($workers as $worker)<option value="{{ $worker->id }}" @selected((string)\App\Support\OperationForm::old('worker_id',$document->worker_id)===(string)$worker->id)>{{ $worker->codigo }} · {{ $worker->nombres }} {{ $worker->apellidos }}</option>@endforeach</select></label>
@endif
<label>Fecha de operación<input type="date" name="document_date" required value="{{ \App\Support\OperationForm::old('document_date',$document->document_date?->format('Y-m-d') ?? now()->timezone('America/La_Paz')->format('Y-m-d')) }}"></label>
@if($kind==='loans')<label>Vencimiento opcional<input type="date" name="due_date" value="{{ \App\Support\OperationForm::old('due_date',$document->due_date?->format('Y-m-d')) }}"></label>@endif
<label>Observaciones<textarea name="notes" maxlength="10000">{{ \App\Support\OperationForm::old('notes',$document->notes) }}</textarea></label>
<div id="operation-lines">
@foreach($rows as $index=>$row)
    @include('worker-operations.line', ['index'=>$index,'row'=>$row])
@endforeach
</div>
<button type="button" id="add-line" class="secondary">Añadir línea</button><button type="submit">Guardar borrador</button>
</form>
<template id="line-template">@include('worker-operations.line', ['index'=>'__INDEX__','row'=>[]])</template>
<script>
(()=>{const form=document.getElementById('operation-form'),container=document.getElementById('operation-lines'),loan=document.getElementById('loan-choice');let next={{ count($rows) }};
const update=()=>{document.getElementById('line-count').value=container.querySelectorAll('fieldset').length;container.querySelectorAll('[data-loan]').forEach(o=>{o.disabled=!!loan && o.dataset.loan!==loan.value;});};
document.getElementById('add-line').addEventListener('click',()=>{if(container.querySelectorAll('fieldset').length>=100)return;container.insertAdjacentHTML('beforeend',document.getElementById('line-template').innerHTML.replaceAll('__INDEX__',next++));update();});
container.addEventListener('click',e=>{if(e.target.matches('[data-remove]')){e.target.closest('fieldset').remove();update();}});if(loan)loan.addEventListener('change',()=>{container.querySelectorAll('[data-loan]').forEach(o=>{if(o.dataset.loan!==loan.value)o.selected=false;});update();});form.addEventListener('submit',update);update();})();
</script>
@endsection
