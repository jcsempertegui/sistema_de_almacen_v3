@extends('layouts.app')
@section('title', 'Atributos por categoría')
@section('content')
<h1>Atributos de {{ $categoria->nombre }}</h1>
<p class="help">Categoría {{ $categoria->codigo }} · {{ $categoria->activo ? 'Activa' : 'Inactiva' }}. Una categoría inactiva conserva su configuración y no admite nuevas asociaciones ni reactivaciones.</p>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('atributos-categoria.index', $categoria) }}"><input name="q" aria-label="Buscar atributos de la categoría" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100"><select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activos</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivos</option></select><button type="submit">Buscar</button></form>
    @can('create', \App\Models\AtributoCategoria::class)
        @if($categoria->activo)<a class="button" href="{{ route('atributos-categoria.create', $categoria) }}">Asociar atributo</a>@endif
    @endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Orden</th><th>Atributo</th><th>Tipo / unidad</th><th>Obligatorio</th><th>Identificación</th><th>Mínimo</th><th>Máximo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($associations as $association)
<tr><td>{{ $association->orden_presentacion }}</td><td>{{ $association->atributo->codigo }} · {{ $association->atributo->nombre }}</td><td>{{ \App\Models\AtributoCatalogo::TYPES[$association->atributo->tipo_dato] }} {{ $association->atributo->unidadMedida?->simbolo }}</td><td>{{ $association->obligatorio ? 'Sí' : 'No' }}</td><td>{{ $association->identificacion_tecnica ? 'Sí' : 'No' }}</td><td>{{ $association->valor_minimo ?? '—' }}</td><td>{{ $association->valor_maximo ?? '—' }}</td><td><span class="badge {{ $association->activo ? '' : 'inactive' }}">{{ $association->activo ? 'Activa' : 'Inactiva' }}</span>@if(! $association->atributo->activo)<span class="help">Atributo inactivo</span>@endif</td><td><div class="actions">
    @can('update', $association)<a href="{{ route('atributos-categoria.edit', [$categoria, $association]) }}">Editar</a>@endcan
    @can('changeStatus', $association)<form method="POST" action="{{ route('atributos-categoria.estado', [$categoria, $association]) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $association->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $association->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="9" class="empty">No se encontraron asociaciones.</td></tr>@endforelse
</tbody></table>{{ $associations->links('pagination.simple') }}</div>
@can('viewAny', \App\Models\Categoria::class)<p><a href="{{ route('categorias.index') }}">Volver a categorías</a></p>@endcan
@endsection
