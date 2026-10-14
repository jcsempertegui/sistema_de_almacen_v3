@extends('layouts.app')
@section('title', 'Unidades de medida')
@section('content')
<h1>Unidades de medida</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('measurement-units.index') }}"><input name="q" aria-label="Buscar unidades de medida" placeholder="Código, nombre o símbolo" value="{{ request('q') }}" maxlength="100"><select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activas</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivas</option></select><button type="submit">Buscar</button></form>
    @can('create', \App\Models\MeasurementUnit::class)<a class="button" href="{{ route('measurement-units.create') }}">Nueva unidad de medida</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Símbolo</th><th>Dimensión</th><th>Decimales</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($units as $unit)
<tr><td>{{ $unit->code }}</td><td>{{ $unit->name }}</td><td>{{ $unit->symbol }}</td><td>{{ \App\Models\MeasurementUnit::DIMENSIONS[$unit->dimension] }}</td><td>{{ $unit->decimal_places }}</td><td><span class="badge {{ $unit->is_active ? '' : 'inactive' }}">{{ $unit->is_active ? 'Activa' : 'Inactiva' }}</span></td><td><div class="actions">
    @can('update', $unit)<a href="{{ route('measurement-units.edit', $unit) }}">Editar</a>@endcan
    @can('changeStatus', $unit)<form method="POST" action="{{ route('measurement-units.status', $unit) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $unit->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $unit->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="7" class="empty">No se encontraron unidades de medida.</td></tr>@endforelse
</tbody></table>{{ $units->links('pagination.simple') }}</div>
@endsection
