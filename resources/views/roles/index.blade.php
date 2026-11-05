@extends('layouts.app')
@section('title', 'Roles y permisos')
@section('content')
<h1>Roles y permisos</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('roles.index') }}">
        <input name="q" aria-label="Buscar roles" placeholder="Nombre o código" value="{{ request('q') }}" maxlength="100">
        <select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activos</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    @can('create', \App\Models\Rol::class)<a class="button" href="{{ route('roles.create') }}">Nuevo rol</a>@endcan
</div>
<div class="card table-wrap">
    <table><thead><tr><th>Rol</th><th>Permisos</th><th>Usuarios</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($roles as $rol)
        <tr><td>{{ $rol->nombre }}<br><span class="help">{{ $rol->codigo }}</span></td><td>{{ $rol->permisos->count() }}</td><td>{{ $rol->usuarios_count }}</td><td>{{ $rol->activo ? 'Activo' : 'Inactivo' }}</td><td><div class="actions">
            @if(auth()->user()->can('update', $rol) || auth()->user()->can('assignPermissions', $rol) || auth()->user()->can('changeStatus', $rol))<a href="{{ route('roles.edit', $rol) }}">Configurar</a>@endif
            @can('changeStatus', $rol)<form method="POST" action="{{ route('roles.estado', $rol) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $rol->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $rol->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan
        </div></td></tr>
    @empty<tr><td colspan="5" class="empty">No se encontraron roles.</td></tr>@endforelse
    </tbody></table>
    {{ $roles->links('pagination.simple') }}
</div>
@endsection
