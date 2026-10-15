@extends('layouts.app')
@section('title', 'Ubicaciones')
@section('content')
<h1>Ubicaciones</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('locations.index') }}">
        <input name="q" aria-label="Buscar ubicaciones" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100">
        <select name="type" aria-label="Filtrar por tipo"><option value="">Todos los tipos</option>@foreach(\App\Models\Location::TYPES as $code => $label)<option value="{{ $code }}" @selected(request('type') === $code)>{{ $label }}</option>@endforeach</select>
        <select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activas</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivas</option></select>
        <select name="parent_id" aria-label="Filtrar por padre"><option value="">Todos los padres</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string) request('parent_id') === (string) $parent->id)>{{ $parent->code }} · {{ $parent->name }}</option>@endforeach</select>
        <label><input type="checkbox" name="roots_only" value="1" @checked(request('roots_only') === '1')> Solo raíces</label>
        <button type="submit">Buscar</button>
    </form>
    <a class="button secondary" href="{{ route('locations.tree') }}">Ver árbol</a>
    @can('create', \App\Models\Location::class)<a class="button" href="{{ route('locations.create') }}">Nueva ubicación</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre y ruta</th><th>Tipo</th><th>Almacenable</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($locations as $location)
<tr><td>{{ $location->code }}</td><td>{{ $location->name }}<span class="help">{{ $paths[$location->id] }}</span></td><td>{{ \App\Models\Location::TYPES[$location->type] }}</td><td>{{ $location->is_storable ? 'Sí' : 'No' }}</td><td><span class="badge {{ $location->is_active ? '' : 'inactive' }}">{{ $location->is_active ? 'Activa' : 'Inactiva' }}</span></td><td><div class="actions">
    @can('update', $location)<a href="{{ route('locations.edit', $location) }}">Editar</a>@endcan
    @can('changeStatus', $location)<form method="POST" action="{{ route('locations.status', $location) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $location->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $location->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="6" class="empty">No se encontraron ubicaciones.</td></tr>@endforelse
</tbody></table>{{ $locations->links('pagination.simple') }}</div>
@endsection
