@extends('layouts.app')
@section('title', 'Trabajadores')
@section('content')
<h1>Trabajadores</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('trabajadores.index') }}">
        <input name="q" aria-label="Buscar trabajadores" placeholder="Código, nombre, documento o área" value="{{ request('q') }}" maxlength="100">
        <select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activos</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    @can('create', \App\Models\Trabajador::class)<a class="button" href="{{ route('trabajadores.create') }}">Nuevo trabajador</a>@endcan
</div>
<div class="card table-wrap">
    <table><thead><tr><th>Código</th><th>Trabajador</th><th>Documento</th><th>Cargo / Área</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($trabajadores as $trabajador)
    <tr><td>{{ $trabajador->codigo }}</td><td>{{ $trabajador->nombres }} {{ $trabajador->apellidos }}</td><td>{{ $trabajador->documento_identidad ?? '—' }}</td><td>{{ $trabajador->cargo ?? '—' }} / {{ $trabajador->area ?? '—' }}</td><td><span class="badge {{ $trabajador->activo ? '' : 'inactive' }}">{{ $trabajador->activo ? 'Activo' : 'Inactivo' }}</span></td><td>
        <div class="actions"><a href="{{ route('trabajadores.show', $trabajador) }}">Ver</a>
        @can('update', $trabajador)
            <a href="{{ route('trabajadores.edit', $trabajador) }}">Editar</a>
        @endcan
        @can('changeStatus', $trabajador)
            <form method="POST" action="{{ route('trabajadores.estado', $trabajador) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $trabajador->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $trabajador->activo ? 'Desactivar' : 'Activar' }}</button></form>
        @endcan</div>
    </td></tr>
    @empty<tr><td colspan="6" class="empty">No se encontraron trabajadores.</td></tr>@endforelse
    </tbody></table>
    {{ $trabajadores->links('pagination.simple') }}
</div>
@endsection
