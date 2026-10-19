@extends('layouts.app')
@section('title', $document->code)
@section('content')
<h1>{{ $label }} · {{ $document->code }}</h1>
<p>Estado: {{ ['draft'=>'Borrador','confirmed'=>'Confirmado','cancelled'=>'Cancelado'][$document->status] }} · Fecha: {{ $document->document_date->format('Y-m-d') }}</p>
<p>Trabajador: {{ $document->worker_snapshot['codigo'] ?? $document->worker->codigo }} · {{ $document->worker_snapshot['nombres'] ?? $document->worker->nombres }} {{ $document->worker_snapshot['apellidos'] ?? $document->worker->apellidos }}</p>
@if($document->confirmed_at)<p>Confirmado por usuario #{{ $document->confirmed_by }} el {{ $document->confirmed_at->timezone('America/La_Paz')->format('Y-m-d H:i:s') }}.</p>@endif
@if($document->cancelled_at)<p>Cancelado por usuario #{{ $document->cancelled_by }}. Motivo: {{ $document->cancellation_reason }}</p>@endif
@if($kind==='loans')<p>Responsabilidad: {{ ['pending'=>'Pendiente','partial'=>'Parcialmente devuelto','returned'=>'Devuelto','draft'=>'Borrador','cancelled'=>'Cancelado'][$document->responsibility_status] }} · Vencimiento: {{ $document->due_date?->format('Y-m-d') ?? 'Sin vencimiento' }}</p>@endif
@if($kind==='returns')<p>Préstamo original: {{ $document->loan->code }}</p>@endif
<p>{{ $document->notes }}</p>
<div class="card table-wrap"><table><thead><tr><th>Línea / ítem</th><th>Producto</th><th>Ubicación</th><th>Cantidad</th><th>Identidades</th></tr></thead><tbody>@foreach($document->lines as $line)<tr><td>{{ $line->line_number ?? $line->id }}</td><td>{{ $line->product->internal_code }} · {{ $line->product->name }}</td><td>{{ $line->location->code }}</td><td>{{ $line->quantity ?? '1' }}</td><td>{{ $kind==='deliveries' ? ($line->units->pluck('identifier')->join(', ') ?: 'Por cantidad') : $line->unit->identifier }}</td></tr>@endforeach</tbody></table></div>
@can('update',$document)<a class="button" href="{{ route('worker-operations.edit',[$kind,$document->id]) }}">Editar borrador</a>@endcan
@if($document->status==='draft')
@can('confirm',$document)<form method="POST" action="{{ route('worker-operations.confirm',[$kind,$document->id]) }}" class="card">@csrf<input type="hidden" name="lock_version" value="{{ $document->lock_version }}"><p>Confirma únicamente después de verificar la operación física y las identidades.</p><button>Confirmar operación física</button></form>@endcan
@can('cancel',$document)<form method="POST" action="{{ route('worker-operations.cancel',[$kind,$document->id]) }}" class="card">@csrf<input type="hidden" name="lock_version" value="{{ $document->lock_version }}"><label>Motivo de cancelación<input name="reason" required maxlength="500"></label><button class="secondary">Cancelar borrador</button></form>@endcan
@endif
@if($kind==='returns')
<h2>Regularizaciones de referencias inactivas</h2><p>No reactivan el catálogo. Cada aprobación se limita a esta herramienta y destino, vence en 24 horas y se utiliza solo con el ingreso físico.</p>
@foreach($document->lines as $line)<section class="card"><h3>{{ $line->unit->identifier }} · destino {{ $line->location->code }}</h3>
@if($document->status==='draft')@can('returns.request_regularization')<form method="POST" action="{{ route('return-regularizations.request',$line) }}">@csrf<input type="hidden" name="lock_version" value="{{ $document->lock_version }}"><label>Motivo de solicitud<input name="reason" required maxlength="1000"></label><button>Solicitar autorización específica</button></form>@endcan @endif
@foreach($line->authorizations as $authorization)<p>Solicitud #{{ $authorization->id }} · {{ ['requested'=>'Solicitada','approved'=>'Aprobada','revoked'=>'Revocada','used'=>'Utilizada'][$authorization->status] }} · {{ $authorization->reason }} · solicitante #{{ $authorization->requested_by }} · autorizador {{ $authorization->approved_by ? '#'.$authorization->approved_by : 'Pendiente' }} · vence {{ $authorization->expires_at?->timezone('America/La_Paz')->format('Y-m-d H:i:s') ?? 'Sin aprobar' }}</p>
@if($document->status==='draft')@foreach(['approve'=>'Aprobar','revoke'=>'Revocar'] as $decision=>$text)@if(($decision==='approve' && $authorization->status==='requested') || ($decision==='revoke' && in_array($authorization->status,['requested','approved'])))@can($decision==='approve'?'returns.authorize_regularization':'returns.revoke_regularization')<form method="POST" action="{{ route('return-regularizations.decide',[$authorization,$decision]) }}">@csrf<label>Motivo de {{ strtolower($text) }}<input name="reason" required maxlength="1000"></label><button>{{ $text }}</button></form>@endcan @endif @endforeach @endif
<ul>@foreach($authorization->events as $event)<li>{{ $event->created_at->timezone('America/La_Paz')->format('Y-m-d H:i:s') }} · usuario #{{ $event->actor_id }} · {{ ['requested'=>'Solicitud','approved'=>'Aprobación','revoked'=>'Revocación','used'=>'Utilización'][$event->action] }} · {{ $event->payload['reason'] ?? '' }}</li>@endforeach</ul>
@endforeach</section>@endforeach
@endif
<a href="{{ route('worker-operations.index',$kind) }}">Volver al listado</a>
@endsection
