@extends('layouts.app')
@section('title', 'Atributos técnicos')
@section('content')
<h1>Atributos técnicos</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('attributes.index') }}">
        <input name="q" aria-label="Buscar atributos" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100">
        <select name="data_type" aria-label="Filtrar por tipo"><option value="">Todos los tipos</option>@foreach(\App\Models\CatalogAttribute::TYPES as $code => $label)<option value="{{ $code }}" @selected(request('data_type') === $code)>{{ $label }}</option>@endforeach</select>
        <select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    @can('create', \App\Models\CatalogAttribute::class)<a class="button" href="{{ route('attributes.create') }}">Nuevo atributo</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Unidad técnica</th><th>Ayuda</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($attributes as $attribute)
<tr><td>{{ $attribute->code }}</td><td>{{ $attribute->name }}</td><td>{{ \App\Models\CatalogAttribute::TYPES[$attribute->data_type] }}</td><td>{{ $attribute->measurementUnit?->symbol ?? '—' }}</td><td>{{ $attribute->help_text ?? '—' }}</td><td><span class="badge {{ $attribute->is_active ? '' : 'inactive' }}">{{ $attribute->is_active ? 'Activo' : 'Inactivo' }}</span></td><td><div class="actions">
    @can('update', $attribute)<a href="{{ route('attributes.edit', $attribute) }}">Editar</a>@endcan
    @can('changeStatus', $attribute)<form method="POST" action="{{ route('attributes.status', $attribute) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $attribute->is_active ? 0 : 1 }}"><button class="secondary" type="submit">{{ $attribute->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="7" class="empty">No se encontraron atributos.</td></tr>@endforelse
</tbody></table>{{ $attributes->links('pagination.simple') }}</div>
@endsection
