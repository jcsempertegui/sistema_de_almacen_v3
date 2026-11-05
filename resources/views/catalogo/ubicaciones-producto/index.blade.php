@extends('layouts.app')
@section('title', 'Ubicaciones del producto')
@section('content')
<h1>Ubicaciones de {{ $producto->codigo_interno }} · {{ $producto->nombre }}</h1>
<p>Estas asociaciones conservan destinos del catálogo y no representan cantidades.</p>
@can('create', \App\Models\UbicacionProducto::class)<p><a href="{{ route('ubicaciones-producto.create', $producto) }}">Asociar ubicación</a></p>@endcan
<section class="card"><form method="GET" class="form-grid"><div class="field"><label for="q">Código o nombre de ubicación</label><input id="q" name="q" value="{{ request('q') }}" maxlength="150"></div><div class="field"><label for="estado">Estado de asociación</label><select name="estado" id="estado"><option value="">Todos</option><option value="active" @selected(request('estado') === 'active')>Activa</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactiva</option></select></div><button>Buscar</button></form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Ubicación</th><th>Estado de ubicación</th><th>Asociación</th><th>Acción</th></tr></thead><tbody>
@forelse($links as $link)<tr><td>{{ $paths[$link->ubicacion_id] }}</td><td>{{ $link->ubicacion->activo ? 'Activa' : 'Inactiva' }}</td><td>{{ $link->activo ? 'Activa' : 'Inactiva' }}</td><td>@can('changeStatus', $link)<form method="POST" action="{{ route('ubicaciones-producto.estado', [$producto, $link]) }}">@csrf @method('PATCH')<input type="hidden" name="version_bloqueo" value="{{ $producto->version_bloqueo }}"><input type="hidden" name="activo" value="{{ (int) ! $link->activo }}"><button>{{ $link->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan</td></tr>@empty<tr><td colspan="4">Sin asociaciones.</td></tr>@endforelse
</tbody></table></div>{{ $links->links() }}</section>
@can('view', $producto)<a href="{{ route('productos.show', $producto) }}">Volver al producto</a>@endcan
@endsection
