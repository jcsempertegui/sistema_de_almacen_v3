@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<h1>Usuarios</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('users.index') }}">
        <input name="q" aria-label="Buscar usuarios" placeholder="Nombre o correo" value="{{ request('q') }}" maxlength="100">
        <select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    <a class="button" href="{{ route('users.create') }}">Nuevo usuario</a>
</div>
<div class="card table-wrap">
    <table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($users as $user)
        <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->role->name }}</td><td><span class="badge {{ $user->is_active ? '' : 'inactive' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></td><td>
            <div class="actions"><a href="{{ route('users.edit', $user) }}">Editar</a>
            <form method="POST" action="{{ route('users.status', $user) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $user->is_active ? 'Desactivar' : 'Activar' }}</button></form></div>
        </td></tr>
    @empty<tr><td colspan="5" class="empty">No se encontraron usuarios.</td></tr>@endforelse
    </tbody></table>
    {{ $users->links('pagination.simple') }}
</div>
@endsection
