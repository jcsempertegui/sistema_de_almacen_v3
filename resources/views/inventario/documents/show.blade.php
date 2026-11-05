@extends('layouts.app')
@section('title', $documento->codigo)
@section('content')
<h1>{{ $documento->codigo }} · {{ \App\Models\DocumentoInventario::KINDS[$documento->tipo] }}</h1>
@include('control.compensation-reference')
<section class="card"><dl><dt>Estado</dt><dd>{{ \App\Models\DocumentoInventario::STATES[$documento->estado] }}</dd><dt>Fecha del documento</dt><dd>{{ $documento->fecha_documento->format('Y-m-d') }}</dd>
@if($documento->fecha_corte)<dt>Fecha de corte</dt><dd>{{ $documento->fecha_corte->format('Y-m-d') }}</dd>@endif
<dt>Contraparte</dt><dd>{{ $documento->identificacion_contraparte }} {{ $documento->nombre_contraparte }}</dd><dt>Referencia externa</dt><dd>{{ $documento->referencia_externa ?? 'Sin referencia' }}</dd><dt>Observaciones</dt><dd>{{ $documento->observaciones ?? 'Sin observaciones' }}</dd><dt>Origen</dt><dd>{{ $documento->origen === 'csv' ? 'CSV revisado' : 'Manual' }}</dd>
@if($documento->sha256_origen)<dt>SHA-256 del CSV</dt><dd class="help">{{ $documento->sha256_origen }}</dd>@endif
<dt>Creado / modificado por</dt><dd>Usuario #{{ $documento->creado_por }} / #{{ $documento->modificado_por }}</dd>
@if($documento->fecha_confirmacion)<dt>Confirmado</dt><dd>{{ $documento->fecha_confirmacion->timezone(\App\Models\DocumentoInventario::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s') }} (Bolivia) · Usuario #{{ $documento->confirmado_por }}</dd>@endif
@if($documento->fecha_cancelacion)<dt>Cancelado</dt><dd>{{ $documento->fecha_cancelacion->timezone(\App\Models\DocumentoInventario::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s') }} (Bolivia) · Usuario #{{ $documento->cancelado_por }} · {{ $documento->motivo_cancelacion }}</dd>@endif</dl></section>
@if($coincidences->isNotEmpty())<div class="notice" role="status"><strong>Posible coincidencia documental.</strong> Revisa los documentos de igual tipo, contraparte y referencia antes de confirmar. No implica duplicación certificada ni bloqueo automático.<ul>@foreach($coincidences as $other)<li>{{ $other->codigo }} · {{ \App\Models\DocumentoInventario::STATES[$other->estado] }}</li>@endforeach</ul></div>@endif
@if($importCoincidences->isNotEmpty())<div class="notice" role="status"><strong>El mismo archivo CSV ya fue incorporado a otros borradores.</strong> Revisa estos documentos. Una apertura confirmada o cualquier movimiento previo impedirá una nueva apertura del producto.<ul>@foreach($importCoincidences as $other)<li>{{ $other->codigo }} · {{ \App\Models\DocumentoInventario::STATES[$other->estado] }}</li>@endforeach</ul></div>@endif
<section class="card"><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Ubicación</th><th>Cantidad</th><th>Identificadores</th></tr></thead><tbody>@foreach($documento->lineas as $line)<tr><td>{{ $line->producto->codigo_interno }} · {{ $line->producto->nombre }}</td><td>{{ $line->ubicacion->codigo }} · {{ $line->ubicacion->nombre }}</td><td>{{ $line->cantidad }} {{ $line->simbolo_unidad }}</td><td>{{ $line->unidades->pluck('identificador')->join(', ') ?: 'Control por cantidad' }}</td></tr>@endforeach</tbody></table></div></section>
@if($documento->estado === 'draft')
<p>Revisa cantidades, identificadores y ubicaciones. Confirmar actualiza existencias de forma definitiva; no se puede editar ni cancelar después.</p>
@can('update', $documento)<p><a href="{{ route('documentos-inventario.edit', $documento) }}">Editar borrador</a></p>@endcan
@can('confirm', $documento)<form method="POST" action="{{ route('documentos-inventario.confirm', $documento) }}">@csrf<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}"><button>Confirmar {{ \App\Models\DocumentoInventario::KINDS[$documento->tipo] }}</button></form>@endcan
@can('cancel', $documento)<section class="card"><form method="POST" action="{{ route('documentos-inventario.cancel', $documento) }}">@csrf<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}"><div class="field"><label for="motivo">Motivo de cancelación del borrador</label><input id="motivo" name="motivo" maxlength="500" required></div><button class="secondary">Cancelar borrador</button></form></section>@endcan
@endif
<p><a href="{{ route('documentos-inventario.index') }}">Volver a documentos</a></p>
@endsection
