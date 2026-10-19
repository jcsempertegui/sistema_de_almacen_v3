@extends('layouts.app')
@section('title', $document->code)
@section('content')
<h1>{{ $document->code }} · {{ \App\Models\InventoryDocument::KINDS[$document->kind] }}</h1>
<section class="card"><dl><dt>Estado</dt><dd>{{ \App\Models\InventoryDocument::STATES[$document->status] }}</dd><dt>Fecha del documento</dt><dd>{{ $document->document_date->format('Y-m-d') }}</dd>
@if($document->cutoff_date)<dt>Fecha de corte</dt><dd>{{ $document->cutoff_date->format('Y-m-d') }}</dd>@endif
<dt>Contraparte</dt><dd>{{ $document->counterparty_id }} {{ $document->counterparty_name }}</dd><dt>Referencia externa</dt><dd>{{ $document->external_reference ?? 'Sin referencia' }}</dd><dt>Observaciones</dt><dd>{{ $document->notes ?? 'Sin observaciones' }}</dd><dt>Origen</dt><dd>{{ $document->source === 'csv' ? 'CSV revisado' : 'Manual' }}</dd>
@if($document->source_sha256)<dt>SHA-256 del CSV</dt><dd class="help">{{ $document->source_sha256 }}</dd>@endif
<dt>Creado / modificado por</dt><dd>Usuario #{{ $document->created_by }} / #{{ $document->updated_by }}</dd>
@if($document->confirmed_at)<dt>Confirmado</dt><dd>{{ $document->confirmed_at->timezone(\App\Models\InventoryDocument::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s') }} (Bolivia) · Usuario #{{ $document->confirmed_by }}</dd>@endif
@if($document->cancelled_at)<dt>Cancelado</dt><dd>{{ $document->cancelled_at->timezone(\App\Models\InventoryDocument::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s') }} (Bolivia) · Usuario #{{ $document->cancelled_by }} · {{ $document->cancellation_reason }}</dd>@endif</dl></section>
@if($coincidences->isNotEmpty())<div class="notice" role="status"><strong>Posible coincidencia documental.</strong> Revisa los documentos de igual tipo, contraparte y referencia antes de confirmar. No implica duplicación certificada ni bloqueo automático.<ul>@foreach($coincidences as $other)<li>{{ $other->code }} · {{ \App\Models\InventoryDocument::STATES[$other->status] }}</li>@endforeach</ul></div>@endif
@if($importCoincidences->isNotEmpty())<div class="notice" role="status"><strong>El mismo archivo CSV ya fue incorporado a otros borradores.</strong> Revisa estos documentos. Una apertura confirmada o cualquier movimiento previo impedirá una nueva apertura del producto.<ul>@foreach($importCoincidences as $other)<li>{{ $other->code }} · {{ \App\Models\InventoryDocument::STATES[$other->status] }}</li>@endforeach</ul></div>@endif
<section class="card"><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Ubicación</th><th>Cantidad</th><th>Identificadores</th></tr></thead><tbody>@foreach($document->lines as $line)<tr><td>{{ $line->product->internal_code }} · {{ $line->product->name }}</td><td>{{ $line->location->code }} · {{ $line->location->name }}</td><td>{{ $line->quantity }} {{ $line->unit_symbol }}</td><td>{{ $line->units->pluck('identifier')->join(', ') ?: 'Control por cantidad' }}</td></tr>@endforeach</tbody></table></div></section>
@if($document->status === 'draft')
<p>Revisa cantidades, identificadores y ubicaciones. Confirmar actualiza existencias de forma definitiva; no se puede editar ni cancelar después.</p>
@can('update', $document)<p><a href="{{ route('inventory-documents.edit', $document) }}">Editar borrador</a></p>@endcan
@can('confirm', $document)<form method="POST" action="{{ route('inventory-documents.confirm', $document) }}">@csrf<input type="hidden" name="lock_version" value="{{ $document->lock_version }}"><button>Confirmar {{ \App\Models\InventoryDocument::KINDS[$document->kind] }}</button></form>@endcan
@can('cancel', $document)<section class="card"><form method="POST" action="{{ route('inventory-documents.cancel', $document) }}">@csrf<input type="hidden" name="lock_version" value="{{ $document->lock_version }}"><div class="field"><label for="reason">Motivo de cancelación del borrador</label><input id="reason" name="reason" maxlength="500" required></div><button class="secondary">Cancelar borrador</button></form></section>@endcan
@endif
<p><a href="{{ route('inventory-documents.index') }}">Volver a documentos</a></p>
@endsection
