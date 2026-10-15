@extends('layouts.app')
@section('title', 'Ubicaciones del producto')
@section('content')
<h1>Ubicaciones de {{ $product->internal_code }} · {{ $product->name }}</h1>
<p>Estas asociaciones conservan destinos del catálogo y no representan cantidades.</p>
@can('create', \App\Models\ProductLocation::class)<p><a href="{{ route('product-locations.create', $product) }}">Asociar ubicación</a></p>@endcan
<section class="card"><form method="GET" class="form-grid"><div class="field"><label for="q">Código o nombre de ubicación</label><input id="q" name="q" value="{{ request('q') }}" maxlength="150"></div><div class="field"><label for="status">Estado de asociación</label><select name="status" id="status"><option value="">Todos</option><option value="active" @selected(request('status') === 'active')>Activa</option><option value="inactive" @selected(request('status') === 'inactive')>Inactiva</option></select></div><button>Buscar</button></form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Ubicación</th><th>Estado de ubicación</th><th>Asociación</th><th>Acción</th></tr></thead><tbody>
@forelse($links as $link)<tr><td>{{ $paths[$link->location_id] }}</td><td>{{ $link->location->is_active ? 'Activa' : 'Inactiva' }}</td><td>{{ $link->is_active ? 'Activa' : 'Inactiva' }}</td><td>@can('changeStatus', $link)<form method="POST" action="{{ route('product-locations.status', [$product, $link]) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $product->lock_version }}"><input type="hidden" name="is_active" value="{{ (int) ! $link->is_active }}"><button>{{ $link->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan</td></tr>@empty<tr><td colspan="4">Sin asociaciones.</td></tr>@endforelse
</tbody></table></div>{{ $links->links() }}</section>
@can('view', $product)<a href="{{ route('products.show', $product) }}">Volver al producto</a>@endcan
@endsection
