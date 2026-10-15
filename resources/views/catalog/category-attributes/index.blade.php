@extends('layouts.app')
@section('title', 'Atributos por categoría')
@section('content')
<h1>Atributos de {{ $category->name }}</h1>
<p class="help">Categoría {{ $category->code }} · {{ $category->is_active ? 'Activa' : 'Inactiva' }}. Una categoría inactiva conserva su configuración y no admite nuevas asociaciones ni reactivaciones.</p>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('category-attributes.index', $category) }}"><input name="q" aria-label="Buscar atributos de la categoría" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100"><select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select><button type="submit">Buscar</button></form>
    @can('create', \App\Models\CategoryAttribute::class)
        @if($category->is_active)<a class="button" href="{{ route('category-attributes.create', $category) }}">Asociar atributo</a>@endif
    @endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Orden</th><th>Atributo</th><th>Tipo / unidad</th><th>Obligatorio</th><th>Identificación</th><th>Mínimo</th><th>Máximo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($associations as $association)
<tr><td>{{ $association->display_order }}</td><td>{{ $association->attribute->code }} · {{ $association->attribute->name }}</td><td>{{ \App\Models\CatalogAttribute::TYPES[$association->attribute->data_type] }} {{ $association->attribute->measurementUnit?->symbol }}</td><td>{{ $association->is_required ? 'Sí' : 'No' }}</td><td>{{ $association->is_identity ? 'Sí' : 'No' }}</td><td>{{ $association->min_value ?? '—' }}</td><td>{{ $association->max_value ?? '—' }}</td><td><span class="badge {{ $association->is_active ? '' : 'inactive' }}">{{ $association->is_active ? 'Activa' : 'Inactiva' }}</span>@if(! $association->attribute->is_active)<span class="help">Atributo inactivo</span>@endif</td><td><div class="actions">
    @can('update', $association)<a href="{{ route('category-attributes.edit', [$category, $association]) }}">Editar</a>@endcan
    @can('changeStatus', $association)<form method="POST" action="{{ route('category-attributes.status', [$category, $association]) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $association->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $association->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="9" class="empty">No se encontraron asociaciones.</td></tr>@endforelse
</tbody></table>{{ $associations->links('pagination.simple') }}</div>
@can('viewAny', \App\Models\Category::class)<p><a href="{{ route('categories.index') }}">Volver a categorías</a></p>@endcan
@endsection
