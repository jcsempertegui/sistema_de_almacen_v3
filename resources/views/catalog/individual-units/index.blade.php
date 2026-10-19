@extends('layouts.app')
@section('title', 'Unidades individualizadas')
@section('content')
<h1>Unidades individualizadas</h1>
<p>Identidades de productos, herramientas y equipos. El estado de catálogo no representa disponibilidad física.</p>
@can('create', \App\Models\IndividualUnit::class)<p><a href="{{ route('individual-units.create', ['product_id' => request('product_id')]) }}">Registrar unidad</a></p>@endcan
<section class="card"><form method="GET" class="form-grid">
<div class="field"><label for="q">Identificador o número de serie</label><input name="q" id="q" value="{{ request('q') }}" maxlength="100"></div>
<div class="field"><label for="product_id">Producto</label><select id="product_id" name="product_id"><option value="">Todos</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->internal_code }} · {{ $product->name }}</option>@endforeach</select></div>
<div class="field"><label for="location_id">Ubicación inicial</label><select id="location_id" name="location_id"><option value="">Todas</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $paths[$location->id] }}</option>@endforeach</select></div>
<div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="">Todos</option><option value="active" @selected(request('status') === 'active')>Activa</option><option value="inactive" @selected(request('status') === 'inactive')>Inactiva</option></select></div><button>Buscar</button>
</form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Identificador</th><th>Producto</th><th>Serie</th><th>Ubicación inicial</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($units as $unit)<tr><td><a href="{{ route('individual-units.show', $unit) }}">{{ $unit->identifier }}</a></td><td>{{ $unit->product->internal_code }} · {{ $unit->product->name }}</td><td>{{ $unit->serial_number ?? 'Sin serie' }}</td><td>{{ $unit->location_id ? $paths[$unit->location_id] : 'Sin ubicación inicial' }}</td><td>{{ $unit->is_active ? 'Activa' : 'Inactiva' }}</td><td>@can('update', $unit)<a href="{{ route('individual-units.edit', $unit) }}">Editar</a>@endcan @can('changeStatus', $unit)<form method="POST" action="{{ route('individual-units.status', $unit) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $unit->lock_version }}"><input type="hidden" name="is_active" value="{{ (int) ! $unit->is_active }}"><button>{{ $unit->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan</td></tr>@empty<tr><td colspan="6">Sin unidades individualizadas.</td></tr>@endforelse
</tbody></table></div>{{ $units->links() }}</section>
@endsection
