@extends('layouts.app')
@section('title', $documento->codigo)
@section('content')
@if(in_array($clase, ['loans', 'returns']))@include('operaciones-trabajadores.tabs')@endif
<h1>{{ $label }} · {{ $documento->codigo }}</h1>
@include('control.compensation-reference')
<p>Estado: {{ ['draft'=>'Borrador','confirmed'=>'Confirmado','cancelled'=>'Cancelado'][$documento->estado] }} · Fecha: {{ $documento->fecha_documento->format('Y-m-d') }}</p>
<p>Trabajador: {{ $documento->datos_historicos_trabajador['codigo'] ?? $documento->trabajador->codigo }} · {{ $documento->datos_historicos_trabajador['nombres'] ?? $documento->trabajador->nombres }} {{ $documento->datos_historicos_trabajador['apellidos'] ?? $documento->trabajador->apellidos }}</p>
@if($documento->fecha_confirmacion)<p>Confirmado por usuario #{{ $documento->confirmado_por }} el {{ $documento->fecha_confirmacion->timezone('America/La_Paz')->format('Y-m-d H:i:s') }}.</p>@endif
@if($documento->fecha_cancelacion)<p>Cancelado por usuario #{{ $documento->cancelado_por }}. Motivo: {{ $documento->motivo_cancelacion }}</p>@endif
@if($clase==='loans')<p>Responsabilidad: {{ ['compensated'=>'Compensado','pending'=>'Pendiente','partial'=>'Parcialmente devuelto','returned'=>'Devuelto','draft'=>'Borrador','cancelled'=>'Cancelado'][$documento->estado_responsabilidad] }} · Vencimiento: {{ $documento->fecha_limite?->format('Y-m-d') ?? 'Sin vencimiento' }}</p>@endif
@if($clase === 'loans' && $documento->estado === 'confirmed' && $documento->lineas()->pending()->exists())
@can('returns.create')<p><a class="button" href="{{ route('operaciones-trabajadores.create', ['clase' => 'returns', 'prestamo_id' => $documento->id]) }}">Registrar devolución de este préstamo</a></p>@endcan
@endif
@if($clase==='returns')<p>Préstamo original: {{ $documento->prestamo->codigo }}</p>@endif
<p>{{ $documento->observaciones }}</p>
<div class="card table-wrap"><table><thead><tr><th>Línea / ítem</th><th>Producto</th><th>Ubicación</th><th>Cantidad</th><th>Identidades</th></tr></thead><tbody>@foreach($documento->lineas as $line)<tr><td>{{ $line->numero_linea ?? $line->id }}</td><td>{{ $line->producto->codigo_interno }} · {{ $line->producto->nombre }}</td><td>{{ $line->ubicacion->codigo }}</td><td>{{ $line->cantidad ?? '1' }}</td><td>{{ $clase==='deliveries' ? ($line->unidades->pluck('identificador')->join(', ') ?: 'Por cantidad') : $line->unidad->identificador }}</td></tr>@endforeach</tbody></table></div>
@can('update',$documento)<a class="button" href="{{ route('operaciones-trabajadores.edit',[$clase,$documento->id]) }}">Editar borrador</a>@endcan
@if($documento->estado==='draft')
@can('confirm',$documento)<form method="POST" action="{{ route('operaciones-trabajadores.confirm',[$clase,$documento->id]) }}" class="card">@csrf<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}"><p>Confirma únicamente después de verificar la operación física y las identidades.</p><button>Confirmar operación física</button></form>@endcan
@can('cancel',$documento)<form method="POST" action="{{ route('operaciones-trabajadores.cancel',[$clase,$documento->id]) }}" class="card">@csrf<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}"><label>Motivo de cancelación<input name="motivo" required maxlength="500"></label><button class="secondary">Cancelar borrador</button></form>@endcan
@endif
@if($clase==='returns')
<h2>Regularizaciones de referencias inactivas</h2><p>No reactivan el catálogo. Cada aprobación se limita a esta herramienta y destino, vence en 24 horas y se utiliza solo con el ingreso físico.</p>
@foreach($documento->lineas as $line)<section class="card"><h3>{{ $line->unidad->identificador }} · destino {{ $line->ubicacion->codigo }}</h3>
@if($documento->estado==='draft')@can('returns.request_regularization')<form method="POST" action="{{ route('regularizaciones-devolucion.request',$line) }}">@csrf<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}"><label>Motivo de solicitud<input name="motivo" required maxlength="1000"></label><button>Solicitar autorización específica</button></form>@endcan @endif
@foreach($line->autorizaciones as $authorization)<p>Solicitud #{{ $authorization->id }} · {{ ['requested'=>'Solicitada','approved'=>'Aprobada','revoked'=>'Revocada','used'=>'Utilizada'][$authorization->estado] }} · {{ $authorization->motivo }} · solicitante #{{ $authorization->solicitado_por }} · autorizador {{ $authorization->autorizado_por ? '#'.$authorization->autorizado_por : 'Pendiente' }} · vence {{ $authorization->fecha_vencimiento?->timezone('America/La_Paz')->format('Y-m-d H:i:s') ?? 'Sin aprobar' }}</p>
@if($documento->estado==='draft')@foreach(['approve'=>'Aprobar','revoke'=>'Revocar'] as $decision=>$text)@if(($decision==='approve' && $authorization->estado==='requested') || ($decision==='revoke' && in_array($authorization->estado,['requested','approved'])))@can($decision==='approve'?'returns.authorize_regularization':'returns.revoke_regularization')<form method="POST" action="{{ route('regularizaciones-devolucion.decide',[$authorization,$decision]) }}">@csrf<label>Motivo de {{ strtolower($text) }}<input name="motivo" required maxlength="1000"></label><button>{{ $text }}</button></form>@endcan @endif @endforeach @endif
<ul>@foreach($authorization->eventos as $event)<li>{{ $event->fecha_creacion->timezone('America/La_Paz')->format('Y-m-d H:i:s') }} · usuario #{{ $event->actor_id }} · {{ ['requested'=>'Solicitud','approved'=>'Aprobación','revoked'=>'Revocación','used'=>'Utilización'][$event->accion] }} · {{ $event->detalle['motivo'] ?? '' }}</li>@endforeach</ul>
@endforeach</section>@endforeach
@endif
<a href="{{ route('operaciones-trabajadores.index',$clase) }}">Volver al listado</a>
@endsection
