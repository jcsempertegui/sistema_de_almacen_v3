@extends('layouts.app')
@section('title', 'Asociar ubicación')
@section('content')
<h1>Asociar ubicación a {{ $producto->codigo_interno }}</h1>
<section class="card"><form method="POST" action="{{ route('ubicaciones-producto.store', $producto) }}">@csrf
<input type="hidden" name="version_bloqueo" value="{{ old('version_bloqueo', $producto->version_bloqueo) }}"><input type="hidden" name="activo" value="1">
<div class="field"><label for="ubicacion_id">Ubicación activa y almacenable</label><select id="ubicacion_id" name="ubicacion_id" required><option value="">Seleccionar</option>@foreach($ubicaciones as $ubicacion)<option value="{{ $ubicacion->id }}" @selected((string) old('ubicacion_id') === (string) $ubicacion->id)>{{ $paths[$ubicacion->id] }}</option>@endforeach</select></div>
<p class="help">Las asociaciones existentes deben reactivarse. Esta operación no registra cantidades.</p><button>Guardar asociación</button>
</form></section>
@endsection
