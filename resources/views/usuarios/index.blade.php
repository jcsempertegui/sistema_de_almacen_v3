@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<h1>Usuarios</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('usuarios.index') }}">
        <input name="q" aria-label="Buscar usuarios" placeholder="Nombre o correo" value="{{ request('q') }}" maxlength="100">
        <select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activos</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    @can('create', \App\Models\Usuario::class)<a class="button" href="{{ route('usuarios.create') }}">Nuevo usuario</a>@endcan
</div>
<div class="card table-wrap">
    <table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($usuarios as $usuario)
        <tr><td>{{ $usuario->nombre }}</td><td>{{ $usuario->correo }}</td><td>{{ $usuario->rol->nombre }}</td><td><span class="badge {{ $usuario->activo ? '' : 'inactive' }}">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</span></td><td>
            <div class="actions">@can('update', $usuario)<a href="{{ route('usuarios.edit', $usuario) }}">Editar</a>@endcan
            @can('changeStatus', $usuario)<form method="POST" action="{{ route('usuarios.estado', $usuario) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $usuario->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $usuario->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan</div>
        </td></tr>
    @empty<tr><td colspan="5" class="empty">No se encontraron usuarios.</td></tr>@endforelse
    </tbody></table>
    {{ $usuarios->links('pagination.simple') }}
</div>
@endsection
