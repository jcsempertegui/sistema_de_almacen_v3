@extends('layouts.app')
@section('title',$document->code)
@section('content')
@include('control.compensation-reference')
<h1>{{ $document->code }} · {{ $kind==='adjustment'?'Ajuste':'Compensación' }}</h1><p>Estado: {{ __('control.states.'.$document->status, [], 'es') }} · {{ $document->document_date->format('Y-m-d') }}</p><p>{{ $document->reason }}</p>
@if($document->source_id)<p>Documento original: {{ __('control.sources.'.$document->source_kind, [], 'es') }} #{{ $document->source_id }}. Su historial se conserva.</p>@endif
@if($document->executed_at)<p>Ejecutado por #{{ $document->executed_by }} el {{ $document->executed_at->timezone('America/La_Paz')->format('Y-m-d H:i:s') }}.</p>@endif
<div class="table-wrap"><table><thead><tr><th>Producto</th><th>Ubicación</th><th>Efecto</th><th>Saldo observado</th><th>Conteo</th><th>Identidades</th></tr></thead><tbody>@foreach($document->lines as $line)<tr><td>{{ $line->product->internal_code }}</td><td>{{ $line->location->code }}</td><td>{{ $line->direction==='in'?'Ingreso':'Egreso' }} {{ $line->quantity }} {{ $line->unit_symbol }}</td><td>{{ $line->observed_quantity }}</td><td>{{ $line->counted_quantity??'No aplica' }}</td><td>{{ $line->units->pluck('identifier')->join(', ')?:'Por cantidad' }}</td></tr>@endforeach</tbody></table></div>
@can('update',$document)<a href="{{ route('corrections.edit',[$kind,$document->id]) }}">Editar y revocar autorizaciones anteriores</a>@endcan
@php($prefix=$kind==='adjustment'?'adjustments':'compensations')
@php($allowedActions=['draft'=>['request','cancel'],'requested'=>['approve','reject','revoke','cancel'],'authorized'=>['execute','revoke','cancel'],'rejected'=>['request','cancel']][$document->status]??[])
@foreach(['request'=>[$prefix.'.request','Solicitar autorización'],'approve'=>['corrections.authorize','Autorizar'],'reject'=>['corrections.authorize','Rechazar'],'revoke'=>['corrections.revoke','Revocar autorización'],'cancel'=>[$prefix.'.cancel','Cancelar pendiente'],'execute'=>[$prefix.'.execute','Ejecutar corrección física']] as $action=>$data)
@can($data[0])@if(in_array($action,$allowedActions))<form method="POST" action="{{ route('corrections.transition',[$kind,$document->id,$action]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $document->lock_version }}">@if(in_array($action,['approve','reject','revoke']))<label>Motivo de la decisión<input name="reason" required maxlength="1000"></label>@endif<button type="submit">{{ $data[1] }}</button></form>@endif
@endcan
@endforeach
<h2>Autorizaciones conservadas</h2><ul>@foreach($document->authorizations as $a)<li>#{{ $a->id }} · {{ __('control.states.'.$a->status, [], 'es') }} · Solicitante #{{ $a->requested_by }} / autorizador #{{ $a->approved_by??'pendiente' }} · {{ $a->reason }} @if($a->expires_at)· Vence {{ $a->expires_at->timezone('America/La_Paz')->format('Y-m-d H:i:s') }}@endif</li>@endforeach</ul>
@endsection
