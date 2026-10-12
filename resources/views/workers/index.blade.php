@extends('layouts.app')
@section('title', 'Trabajadores')
@section('content')
<h1>Trabajadores</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('workers.index') }}">
        <input name="q" aria-label="Buscar trabajadores" placeholder="Código, nombre, documento o área" value="{{ request('q') }}" maxlength="100">
        <select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    @can('create', \App\Models\Worker::class)<a class="button" href="{{ route('workers.create') }}">Nuevo trabajador</a>@endcan
</div>
<div class="card table-wrap">
    <table><thead><tr><th>Código</th><th>Trabajador</th><th>Documento</th><th>Cargo / Área</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($workers as $worker)
    <tr><td>{{ $worker->codigo }}</td><td>{{ $worker->nombres }} {{ $worker->apellidos }}</td><td>{{ $worker->documento_identidad ?? '—' }}</td><td>{{ $worker->cargo ?? '—' }} / {{ $worker->area ?? '—' }}</td><td><span class="badge {{ $worker->is_active ? '' : 'inactive' }}">{{ $worker->is_active ? 'Activo' : 'Inactivo' }}</span></td><td>
        <div class="actions"><a href="{{ route('workers.show', $worker) }}">Ver</a>
        @can('update', $worker)
            <a href="{{ route('workers.edit', $worker) }}">Editar</a>
            <form method="POST" action="{{ route('workers.status', $worker) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $worker->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $worker->is_active ? 'Desactivar' : 'Activar' }}</button></form>
        @endcan</div>
    </td></tr>
    @empty<tr><td colspan="6" class="empty">No se encontraron trabajadores.</td></tr>@endforelse
    </tbody></table>
    {{ $workers->links('pagination.simple') }}
</div>
@endsection
