@extends('layouts.app')
@section('title', $unidad->identificador)
@section('content')
<h1>Unidad {{ $unidad->identificador }}</h1>
<section class="card"><dl>
<dt>Producto</dt><dd>{{ $unidad->producto->codigo_interno }} · {{ $unidad->producto->nombre }}</dd>
<dt>Número de serie</dt><dd>{{ $unidad->numero_serie ?? 'Sin serie' }}</dd>
<dt>Ubicación inicial</dt><dd>{{ $unidad->ubicacion_id ? $paths[$unidad->ubicacion_id] : 'Sin ubicación inicial' }}</dd>
<dt>Estado de catálogo</dt><dd>{{ $unidad->activo ? 'Activa' : 'Inactiva' }}</dd>
<dt>Observaciones</dt><dd>{{ $unidad->observaciones ?? 'Sin observaciones' }}</dd>
<dt>Creación</dt><dd>{{ $unidad->fecha_creacion }} · {{ $unidad->creador?->nombre ?? 'Sin autor informado' }}</dd>
<dt>Última modificación</dt><dd>{{ $unidad->fecha_actualizacion }} · {{ $unidad->modificador?->nombre ?? 'Sin autor informado' }}</dd>
</dl><p class="help">Este registro conserva la identidad y no representa un ingreso ni un saldo de existencias.</p>
<div class="actions">@can('update', $unidad)<a href="{{ route('unidades-individuales.edit', $unidad) }}">Editar</a>@endcan @can('viewAny', \App\Models\UnidadIndividual::class)<a href="{{ route('unidades-individuales.index') }}">Volver al listado</a>@endcan</div>
@can('changeStatus', $unidad)<form method="POST" action="{{ route('unidades-individuales.estado', $unidad) }}">@csrf @method('PATCH')<input type="hidden" name="version_bloqueo" value="{{ $unidad->version_bloqueo }}"><input type="hidden" name="activo" value="{{ (int) ! $unidad->activo }}"><button>{{ $unidad->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</section>
@endsection
