@extends('layouts.app')
@section('title', 'Revisión de apertura')
@section('content')
<h1>Revisión de apertura importada</h1><p>Revisa códigos, cantidades e identificadores antes de guardar. Se volverán a validar permisos y catálogo al guardar y al confirmar. La revisión caduca en 15 minutos.</p><p>Fecha: {{ $documentDate }}. Corte del lote: {{ $cutoffDate }}.</p><p class="help">SHA-256: {{ $hash }}</p>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Ubicación</th><th>Cantidad</th><th>Unidades seleccionadas</th></tr></thead><tbody>@foreach($lineas as $line)<tr><td>{{ $productos[$line['producto_id']] }}</td><td>{{ $ubicaciones[$line['ubicacion_id']] }}</td><td>{{ $line['cantidad'] }}</td><td>{{ collect($line['unit_ids'])->map(fn ($id) => $identifiers[$id])->join(', ') ?: 'Por cantidad' }}</td></tr>@endforeach</tbody></table></div></section>
<form method="POST" action="{{ route('importacion-existencias-iniciales.store') }}">@csrf<input name="token" type="hidden" value="{{ $token }}"><button>Guardar como borrador de apertura</button></form><p><a href="{{ route('importacion-existencias-iniciales.create') }}">Elegir otro archivo</a></p>
@endsection
