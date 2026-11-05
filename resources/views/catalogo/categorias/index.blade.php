@extends('layouts.app')
@section('title', 'Categorías')
@section('content')
<h1>Categorías</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('categorias.index') }}"><input name="q" aria-label="Buscar categorías" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100"><select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activas</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivas</option></select><button type="submit">Buscar</button></form>
    @can('create', \App\Models\Categoria::class)<a class="button" href="{{ route('categorias.create') }}">Nueva categoría</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($categorias as $categoria)
<tr><td>{{ $categoria->codigo }}</td><td>{{ $categoria->nombre }}</td><td>{{ $categoria->descripcion ?? '—' }}</td><td><span class="badge {{ $categoria->activo ? '' : 'inactive' }}">{{ $categoria->activo ? 'Activa' : 'Inactiva' }}</span></td><td><div class="actions">
    @can('viewAny', \App\Models\AtributoCategoria::class)<a href="{{ route('atributos-categoria.index', $categoria) }}">Configurar atributos</a>@endcan
    @can('update', $categoria)<a href="{{ route('categorias.edit', $categoria) }}">Editar</a>@endcan
    @can('changeStatus', $categoria)<form method="POST" action="{{ route('categorias.estado', $categoria) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $categoria->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $categoria->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="5" class="empty">No se encontraron categorías.</td></tr>@endforelse
</tbody></table>{{ $categorias->links('pagination.simple') }}</div>
@endsection
