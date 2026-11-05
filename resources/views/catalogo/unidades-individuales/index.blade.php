@extends('layouts.app')
@section('title', 'Unidades individualizadas')
@section('content')
<h1>Unidades individualizadas</h1>
<p>Identidades de productos, herramientas y equipos. El estado de catálogo no representa disponibilidad física.</p>
@can('create', \App\Models\UnidadIndividual::class)<p><a href="{{ route('unidades-individuales.create', ['producto_id' => request('producto_id')]) }}">Registrar unidad</a></p>@endcan
<section class="card"><form method="GET" class="form-grid">
<div class="field"><label for="q">Identificador o número de serie</label><input name="q" id="q" value="{{ request('q') }}" maxlength="100"></div>
<div class="field"><label for="producto_id">Producto</label><select id="producto_id" name="producto_id"><option value="">Todos</option>@foreach($productos as $producto)<option value="{{ $producto->id }}" @selected((string) request('producto_id') === (string) $producto->id)>{{ $producto->codigo_interno }} · {{ $producto->nombre }}</option>@endforeach</select></div>
<div class="field"><label for="ubicacion_id">Ubicación inicial</label><select id="ubicacion_id" name="ubicacion_id"><option value="">Todas</option>@foreach($ubicaciones as $ubicacion)<option value="{{ $ubicacion->id }}" @selected((string) request('ubicacion_id') === (string) $ubicacion->id)>{{ $paths[$ubicacion->id] }}</option>@endforeach</select></div>
<div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><option value="active" @selected(request('estado') === 'active')>Activa</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactiva</option></select></div><button>Buscar</button>
</form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Identificador</th><th>Producto</th><th>Serie</th><th>Ubicación inicial</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($unidades as $unidad)<tr><td><a href="{{ route('unidades-individuales.show', $unidad) }}">{{ $unidad->identificador }}</a></td><td>{{ $unidad->producto->codigo_interno }} · {{ $unidad->producto->nombre }}</td><td>{{ $unidad->numero_serie ?? 'Sin serie' }}</td><td>{{ $unidad->ubicacion_id ? $paths[$unidad->ubicacion_id] : 'Sin ubicación inicial' }}</td><td>{{ $unidad->activo ? 'Activa' : 'Inactiva' }}</td><td>@can('update', $unidad)<a href="{{ route('unidades-individuales.edit', $unidad) }}">Editar</a>@endcan @can('changeStatus', $unidad)<form method="POST" action="{{ route('unidades-individuales.estado', $unidad) }}">@csrf @method('PATCH')<input type="hidden" name="version_bloqueo" value="{{ $unidad->version_bloqueo }}"><input type="hidden" name="activo" value="{{ (int) ! $unidad->activo }}"><button>{{ $unidad->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan</td></tr>@empty<tr><td colspan="6">Sin unidades individualizadas.</td></tr>@endforelse
</tbody></table></div>{{ $unidades->links() }}</section>
@endsection
