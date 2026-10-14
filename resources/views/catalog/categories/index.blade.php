@extends('layouts.app')
@section('title', 'Categorías')
@section('content')
<h1>Categorías</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('categories.index') }}"><input name="q" aria-label="Buscar categorías" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100"><select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activas</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivas</option></select><button type="submit">Buscar</button></form>
    @can('create', \App\Models\Category::class)<a class="button" href="{{ route('categories.create') }}">Nueva categoría</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($categories as $category)
<tr><td>{{ $category->code }}</td><td>{{ $category->name }}</td><td>{{ $category->description ?? '—' }}</td><td><span class="badge {{ $category->is_active ? '' : 'inactive' }}">{{ $category->is_active ? 'Activa' : 'Inactiva' }}</span></td><td><div class="actions">
    @can('update', $category)<a href="{{ route('categories.edit', $category) }}">Editar</a>@endcan
    @can('changeStatus', $category)<form method="POST" action="{{ route('categories.status', $category) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $category->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="5" class="empty">No se encontraron categorías.</td></tr>@endforelse
</tbody></table>{{ $categories->links('pagination.simple') }}</div>
@endsection
