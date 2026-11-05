@extends('layouts.app')
@section('title', 'Unidades de medida')
@section('content')
<h1>Unidades de medida</h1>
<div class="toolbar">
    <form class="search" method="GET" action="{{ route('unidades-medida.index') }}"><input name="q" aria-label="Buscar unidades de medida" placeholder="Código, nombre o símbolo" value="{{ request('q') }}" maxlength="100"><select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active" @selected(request('estado') === 'active')>Activas</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivas</option></select><button type="submit">Buscar</button></form>
    @can('create', \App\Models\UnidadMedida::class)<a class="button" href="{{ route('unidades-medida.create') }}">Nueva unidad de medida</a>@endcan
</div>
<div class="card table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Símbolo</th><th>Dimensión</th><th>Decimales</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($unidades as $unidad)
<tr><td>{{ $unidad->codigo }}</td><td>{{ $unidad->nombre }}</td><td>{{ $unidad->simbolo }}</td><td>{{ \App\Models\UnidadMedida::DIMENSIONS[$unidad->dimension] }}</td><td>{{ $unidad->decimales }}</td><td><span class="badge {{ $unidad->activo ? '' : 'inactive' }}">{{ $unidad->activo ? 'Activa' : 'Inactiva' }}</span></td><td><div class="actions">
    @can('update', $unidad)<a href="{{ route('unidades-medida.edit', $unidad) }}">Editar</a>@endcan
    @can('changeStatus', $unidad)<form method="POST" action="{{ route('unidades-medida.estado', $unidad) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $unidad->activo ? 0 : 1 }}"><button class="secondary" type="submit">{{ $unidad->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan
</div></td></tr>
@empty<tr><td colspan="7" class="empty">No se encontraron unidades de medida.</td></tr>@endforelse
</tbody></table>{{ $unidades->links('pagination.simple') }}</div>
@endsection
