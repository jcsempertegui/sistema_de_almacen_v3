@extends('layouts.app')
@section('title', 'Atributos técnicos')
@section('content')
<h1>Atributos técnicos</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('atributos.index') }}">
        <input name="q" aria-label="Buscar atributos" placeholder="Código o nombre" value="{{ request('q') }}" maxlength="100">
        <select name="tipo_dato" aria-label="Filtrar por tipo"><option value="">Todos los tipos</option>@foreach(\App\Models\AtributoCatalogo::TYPES as $codigo => $label)<option value="{{ $codigo }}" @selected(request('tipo_dato') === $codigo)>{{ $label }}</option>@endforeach</select>
        <select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activos</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivos</option></select>
        <button type="submit">Buscar</button>
    </form>
    @can('create', \App\Models\AtributoCatalogo::class)<a class="button" href="{{ route('atributos.create') }}">Nuevo atributo</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Unidad técnica</th><th>Ayuda</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($atributos as $atributo)
<tr><td>{{ $atributo->codigo }}</td><td>{{ $atributo->nombre }}</td><td>{{ \App\Models\AtributoCatalogo::TYPES[$atributo->tipo_dato] }}</td><td>{{ $atributo->unidadMedida?->simbolo ?? '—' }}</td><td>{{ $atributo->texto_ayuda ?? '—' }}</td><td><span class="badge {{ $atributo->activo ? '' : 'inactive' }}">{{ $atributo->activo ? 'Activo' : 'Inactivo' }}</span></td><td><div class="actions">
    @can('update', $atributo)<a href="{{ route('atributos.edit', $atributo) }}">Editar</a>@endcan
    @can('changeStatus', $atributo)<form method="POST" action="{{ route('atributos.estado', $atributo) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $atributo->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $atributo->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="7" class="empty">No se encontraron atributos.</td></tr>@endforelse
</tbody></table>{{ $atributos->links('pagination.simple') }}</div>
@endsection
