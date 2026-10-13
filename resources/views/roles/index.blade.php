@extends('layouts.app')
@section('title', 'Roles y permisos')
@section('content')
<h1>Roles y permisos</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('roles.index') }}">
        <input name="q" aria-label="Buscar roles" placeholder="Nombre o código" value="{{ request('q') }}" maxlength="100">
        <select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    @can('create', \App\Models\Role::class)<a class="button" href="{{ route('roles.create') }}">Nuevo rol</a>@endcan
</div>
<div class="card table-wrap">
    <table><thead><tr><th>Rol</th><th>Permisos</th><th>Usuarios</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($roles as $role)
        <tr><td>{{ $role->name }}<br><span class="help">{{ $role->code }}</span></td><td>{{ $role->permissions->count() }}</td><td>{{ $role->users_count }}</td><td>{{ $role->is_active ? 'Activo' : 'Inactivo' }}</td><td><div class="actions">
            @if(auth()->user()->can('update', $role) || auth()->user()->can('assignPermissions', $role) || auth()->user()->can('changeStatus', $role))<a href="{{ route('roles.edit', $role) }}">Configurar</a>@endif
            @can('changeStatus', $role)<form method="POST" action="{{ route('roles.status', $role) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $role->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $role->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
        </div></td></tr>
    @empty<tr><td colspan="5" class="empty">No se encontraron roles.</td></tr>@endforelse
    </tbody></table>
    {{ $roles->links('pagination.simple') }}
</div>
@endsection
