@extends('layouts.app')
@section('title', 'Árbol de ubicaciones')
@section('content')
<h1>Árbol de ubicaciones</h1>
<p class="help">Las raíces tienen nivel 0. Cada ubicación muestra su ruta completa, estado y posibilidad de almacenar. Se conservan también las ubicaciones inactivas.</p>
<p><a href="{{ route('ubicaciones.index') }}">Volver al listado y buscar</a></p>
<div class="card table-wrap"><table><thead><tr><th>Ubicación</th><th>Nivel</th><th>Tipo</th><th>Almacenable</th><th>Estado</th></tr></thead><tbody>
@forelse($rows as $row)
@php($ubicacion = $row['ubicacion'])
<tr><td><span style="padding-inline-start: {{ min($row['depth'], 12) * 1.25 }}rem">{{ $row['depth'] ? '↳ ' : '' }}{{ $ubicacion->codigo }} · {{ $ubicacion->nombre }}</span><span class="help">{{ $paths[$ubicacion->id] }}</span></td><td>{{ $row['depth'] }}</td><td>{{ \App\Models\Ubicacion::TYPES[$ubicacion->tipo] }}</td><td>{{ $ubicacion->permite_almacenar ? 'Sí' : 'No' }}</td><td><span class="badge {{ $ubicacion->activo ? '' : 'inactive' }}">{{ $ubicacion->activo ? 'Activa' : 'Inactiva' }}</span></td></tr>
@empty<tr><td colspan="5" class="empty">No hay ubicaciones registradas.</td></tr>@endforelse
</tbody></table></div>
@endsection
