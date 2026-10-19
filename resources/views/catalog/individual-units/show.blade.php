@extends('layouts.app')
@section('title', $unit->identifier)
@section('content')
<h1>Unidad {{ $unit->identifier }}</h1>
<section class="card"><dl>
<dt>Producto</dt><dd>{{ $unit->product->internal_code }} · {{ $unit->product->name }}</dd>
<dt>Número de serie</dt><dd>{{ $unit->serial_number ?? 'Sin serie' }}</dd>
<dt>Ubicación inicial</dt><dd>{{ $unit->location_id ? $paths[$unit->location_id] : 'Sin ubicación inicial' }}</dd>
<dt>Estado de catálogo</dt><dd>{{ $unit->is_active ? 'Activa' : 'Inactiva' }}</dd>
<dt>Observaciones</dt><dd>{{ $unit->notes ?? 'Sin observaciones' }}</dd>
<dt>Creación</dt><dd>{{ $unit->created_at }} · {{ $unit->creator?->name ?? 'Sin autor informado' }}</dd>
<dt>Última modificación</dt><dd>{{ $unit->updated_at }} · {{ $unit->updater?->name ?? 'Sin autor informado' }}</dd>
</dl><p class="help">Este registro conserva la identidad y no representa un ingreso ni un saldo de existencias.</p>
<div class="actions">@can('update', $unit)<a href="{{ route('individual-units.edit', $unit) }}">Editar</a>@endcan @can('viewAny', \App\Models\IndividualUnit::class)<a href="{{ route('individual-units.index') }}">Volver al listado</a>@endcan</div>
@can('changeStatus', $unit)<form method="POST" action="{{ route('individual-units.status', $unit) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $unit->lock_version }}"><input type="hidden" name="is_active" value="{{ (int) ! $unit->is_active }}"><button>{{ $unit->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</section>
@endsection
