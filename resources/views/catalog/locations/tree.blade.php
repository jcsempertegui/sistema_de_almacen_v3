@extends('layouts.app')
@section('title', 'Árbol de ubicaciones')
@section('content')
<h1>Árbol de ubicaciones</h1>
<p class="help">Las raíces tienen nivel 0. Cada ubicación muestra su ruta completa, estado y posibilidad de almacenar. Se conservan también las ubicaciones inactivas.</p>
<p><a href="{{ route('locations.index') }}">Volver al listado y buscar</a></p>
<div class="card table-wrap"><table><thead><tr><th>Ubicación</th><th>Nivel</th><th>Tipo</th><th>Almacenable</th><th>Estado</th></tr></thead><tbody>
@forelse($rows as $row)
@php($location = $row['location'])
<tr><td><span style="padding-inline-start: {{ min($row['depth'], 12) * 1.25 }}rem">{{ $row['depth'] ? '↳ ' : '' }}{{ $location->code }} · {{ $location->name }}</span><span class="help">{{ $paths[$location->id] }}</span></td><td>{{ $row['depth'] }}</td><td>{{ \App\Models\Location::TYPES[$location->type] }}</td><td>{{ $location->is_storable ? 'Sí' : 'No' }}</td><td><span class="badge {{ $location->is_active ? '' : 'inactive' }}">{{ $location->is_active ? 'Activa' : 'Inactiva' }}</span></td></tr>
@empty<tr><td colspan="5" class="empty">No hay ubicaciones registradas.</td></tr>@endforelse
</tbody></table></div>
@endsection
