@extends('layouts.app')
@section('title', 'Productos')
@section('content')
<h1>Productos</h1>
@can('create', \App\Models\Product::class)<p><a class="button" href="{{ route('products.create') }}">Nuevo producto</a></p>@endcan
<section class="card"><form method="GET" class="form-grid">
    <div class="field"><label for="q">Código, nombre, marca o referencia</label><input id="q" name="q" value="{{ request('q') }}" maxlength="150"></div>
    <div class="field"><label for="category_id">Categoría</label><select id="category_id" name="category_id"><option value="">Todas</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div class="field"><label for="measurement_unit_id">Unidad de medida</label><select id="measurement_unit_id" name="measurement_unit_id"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) request('measurement_unit_id') === (string) $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select></div>
    <div class="field"><label for="control_mode">Modo de control</label><select id="control_mode" name="control_mode"><option value="">Todos</option>@foreach(\App\Models\Product::MODES as $key => $label)<option value="{{ $key }}" @selected(request('control_mode') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="">Todos</option><option value="active" @selected(request('status') === 'active')>Activo</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivo</option></select></div>
    <div class="actions"><button>Buscar</button><a href="{{ route('products.index') }}">Limpiar</a></div>
</form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Unidad</th><th>Control</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($products as $product)<tr>
    <td>{{ $product->internal_code }}</td><td>{{ $product->name }}</td><td>{{ $product->category->name }}</td><td>{{ $product->measurementUnit->symbol }}</td><td>{{ \App\Models\Product::MODES[$product->control_mode] }}{{ $product->is_loanable ? ' · Prestable' : '' }}</td><td>{{ $product->is_active ? 'Activo' : 'Inactivo' }}</td>
    <td><a href="{{ route('products.show', $product) }}">Ver</a> @can('update', $product)<a href="{{ route('products.edit', $product) }}">Editar</a>@endcan
    @can('changeStatus', $product)<form method="POST" action="{{ route('products.status', $product) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $product->lock_version }}"><input type="hidden" name="is_active" value="{{ (int) ! $product->is_active }}"><button class="secondary">{{ $product->is_active ? 'Desactivar' : 'Activar' }}</button></form>@endcan</td>
</tr>@empty<tr><td colspan="7">No hay productos para los filtros seleccionados.</td></tr>@endforelse
</tbody></table></div>{{ $products->links() }}</section>
@endsection
