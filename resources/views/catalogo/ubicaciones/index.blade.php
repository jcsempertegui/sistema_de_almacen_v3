@extends('layouts.app')
@section('title', 'Ubicaciones')
@section('content')
<h1>Ubicaciones</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('ubicaciones.index') }}">
        <input name="q" aria-label="Buscar ubicaciones" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100">
        <select name="tipo" aria-label="Filtrar por tipo"><option value="">Todos los tipos</option>@foreach(\App\Models\Ubicacion::TYPES as $codigo => $label)<option value="{{ $codigo }}" @selected(request('tipo') === $codigo)>{{ $label }}</option>@endforeach</select>
        <select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activas</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivas</option></select>
        <select name="padre_id" aria-label="Filtrar por padre"><option value="">Todos los padres</option>@foreach($parents as $padre)<option value="{{ $padre->id }}" @selected((string) request('padre_id') === (string) $padre->id)>{{ $padre->codigo }} · {{ $padre->nombre }}</option>@endforeach</select>
        <label><input type="checkbox" name="roots_only" value="1" @checked(request('roots_only') === '1')> Solo raíces</label>
        <button type="submit">Buscar</button>
    </form>
    <a class="button secondary" href="{{ route('ubicaciones.tree') }}">Ver árbol</a>
    @can('create', \App\Models\Ubicacion::class)<a class="button" href="{{ route('ubicaciones.create') }}">Nueva ubicación</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre y ruta</th><th>Tipo</th><th>Almacenable</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($ubicaciones as $ubicacion)
<tr><td>{{ $ubicacion->codigo }}</td><td>{{ $ubicacion->nombre }}<span class="help">{{ $paths[$ubicacion->id] }}</span></td><td>{{ \App\Models\Ubicacion::TYPES[$ubicacion->tipo] }}</td><td>{{ $ubicacion->permite_almacenar ? 'Sí' : 'No' }}</td><td><span class="badge {{ $ubicacion->activo ? '' : 'inactive' }}">{{ $ubicacion->activo ? 'Activa' : 'Inactiva' }}</span></td><td><div class="actions">
    @can('update', $ubicacion)<a href="{{ route('ubicaciones.edit', $ubicacion) }}">Editar</a>@endcan
    @can('changeStatus', $ubicacion)<form method="POST" action="{{ route('ubicaciones.estado', $ubicacion) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $ubicacion->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $ubicacion->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="6" class="empty">No se encontraron ubicaciones.</td></tr>@endforelse
</tbody></table>{{ $ubicaciones->links('pagination.simple') }}</div>
@endsection
