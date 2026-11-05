@extends('layouts.app')
@section('title',$documento->codigo)
@section('content')
@include('control.compensation-reference')
<h1>{{ $documento->codigo }} · {{ $clase==='adjustment'?'Ajuste':'Compensación' }}</h1><p>Estado: {{ __('control.states.'.$documento->estado, [], 'es') }} · {{ $documento->fecha_documento->format('Y-m-d') }}</p><p>{{ $documento->motivo }}</p>
@if($documento->origen_id)<p>Documento original: {{ __('control.sources.'.$documento->tipo_origen, [], 'es') }} #{{ $documento->origen_id }}. Su historial se conserva.</p>@endif
@if($documento->fecha_ejecucion)<p>Ejecutado por #{{ $documento->ejecutado_por }} el {{ $documento->fecha_ejecucion->timezone('America/La_Paz')->format('Y-m-d H:i:s') }}.</p>@endif
<div class="table-wrap"><table><thead><tr><th>Producto</th><th>Ubicación</th><th>Efecto</th><th>Saldo observado</th><th>Conteo</th><th>Identidades</th></tr></thead><tbody>@foreach($documento->lineas as $line)<tr><td>{{ $line->producto->codigo_interno }}</td><td>{{ $line->ubicacion->codigo }}</td><td>{{ $line->direccion==='in'?'Ingreso':'Egreso' }} {{ $line->cantidad }} {{ $line->simbolo_unidad }}</td><td>{{ $line->cantidad_observada }}</td><td>{{ $line->cantidad_contada??'No aplica' }}</td><td>{{ $line->unidades->pluck('identificador')->join(', ')?:'Por cantidad' }}</td></tr>@endforeach</tbody></table></div>
@can('update',$documento)<a href="{{ route('corrections.edit',[$clase,$documento->id]) }}">Editar y revocar autorizaciones anteriores</a>@endcan
@php($prefix=$clase==='adjustment'?'adjustments':'compensations')
@php($allowedActions=['draft'=>['request','cancel'],'requested'=>['approve','reject','revoke','cancel'],'authorized'=>['execute','revoke','cancel'],'rejected'=>['request','cancel']][$documento->estado]??[])
@foreach(['request'=>[$prefix.'.request','Solicitar autorización'],'approve'=>['corrections.authorize','Autorizar'],'reject'=>['corrections.authorize','Rechazar'],'revoke'=>['corrections.revoke','Revocar autorización'],'cancel'=>[$prefix.'.cancel','Cancelar pendiente'],'execute'=>[$prefix.'.execute','Ejecutar corrección física']] as $accion=>$data)
@can($data[0])@if(in_array($accion,$allowedActions))<form method="POST" action="{{ route('corrections.transition',[$clase,$documento->id,$accion]) }}">@csrf<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}">@if(in_array($accion,['approve','reject','revoke']))<label>Motivo de la decisión<input name="motivo" required maxlength="1000"></label>@endif<button type="submit">{{ $data[1] }}</button></form>@endif
@endcan
@endforeach
<h2>Autorizaciones conservadas</h2><ul>@foreach($documento->autorizaciones as $a)<li>#{{ $a->id }} · {{ __('control.states.'.$a->estado, [], 'es') }} · Solicitante #{{ $a->solicitado_por }} / autorizador #{{ $a->autorizado_por??'pendiente' }} · {{ $a->motivo }} @if($a->fecha_vencimiento)· Vence {{ $a->fecha_vencimiento->timezone('America/La_Paz')->format('Y-m-d H:i:s') }}@endif</li>@endforeach</ul>
@endsection
