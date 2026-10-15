@extends('layouts.app')
@section('title', $product->exists ? 'Editar producto' : 'Nuevo producto')
@section('content')
<h1>{{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>
<section class="card"><form method="GET" action="{{ $product->exists ? route('products.edit', $product) : route('products.create') }}">
    <div class="field"><label for="choose_category">Selecciona la categoría antes de completar el formulario</label><select id="choose_category" name="category_id" required><option value="">Seleccionar</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}{{ ! $category->is_active ? ' · Inactiva' : '' }}</option>@endforeach</select></div><button>Cargar atributos de la categoría</button>
    <p class="help">Cambiar esta selección recarga el formulario. Los cambios aún no guardados deberán ingresarse nuevamente.</p>
</form></section>
@if($categoryId)
<section class="card"><form method="POST" action="{{ $product->exists ? route('products.preview-update', $product) : route('products.preview') }}">
    @csrf
    <input type="hidden" name="category_id" value="{{ $categoryId }}">
    @if($product->exists)<input type="hidden" name="lock_version" value="{{ old('lock_version', $product->lock_version) }}">@endif
    <div class="form-grid">
        <div class="field"><label for="internal_code">Código interno</label><input id="internal_code" name="internal_code" value="{{ old('internal_code', $product->internal_code) }}" maxlength="60" required @readonly($product->exists)><span class="help">El código se conserva y no puede reutilizarse.</span></div>
        <div class="field"><label for="name">Nombre</label><input id="name" name="name" value="{{ old('name', $product->name) }}" maxlength="150" required></div>
        <div class="field"><label for="measurement_unit_id">Unidad de medida</label><select id="measurement_unit_id" name="measurement_unit_id" required><option value="">Seleccionar</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('measurement_unit_id', $product->measurement_unit_id) === (string) $unit->id)>{{ $unit->name }} ({{ $unit->symbol }}){{ ! $unit->is_active ? ' · Inactiva' : '' }}</option>@endforeach</select></div>
        <div class="field"><label for="brand">Marca</label><input id="brand" name="brand" value="{{ old('brand', $product->brand) }}" maxlength="100"></div>
        <div class="field"><label for="manufacturer_reference">Referencia de fabricante</label><input id="manufacturer_reference" name="manufacturer_reference" value="{{ old('manufacturer_reference', $product->manufacturer_reference) }}" maxlength="100"></div>
        <div class="field"><label for="control_mode">Modo de control</label><select id="control_mode" name="control_mode">@foreach(\App\Models\Product::MODES as $key => $label)<option value="{{ $key }}" @selected(old('control_mode', $product->control_mode ?? 'quantity') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="is_loanable">Prestable</label><select id="is_loanable" name="is_loanable"><option value="0" @selected((string) old('is_loanable', (int) $product->is_loanable) === '0')>No</option><option value="1" @selected((string) old('is_loanable', (int) $product->is_loanable) === '1')>Sí</option></select><span class="help">Un producto prestable requiere control individual.</span></div>
        <div class="field"><label for="description">Descripción</label><textarea id="description" name="description" maxlength="10000">{{ old('description', $product->description) }}</textarea></div>
        <div class="field"><label for="is_active">Estado</label>@if($product->exists && ! auth()->user()->can('changeStatus', $product))<input value="{{ $product->is_active ? 'Activo' : 'Inactivo' }}" disabled><input type="hidden" name="is_active" value="{{ (int) $product->is_active }}">@else<select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $product->is_active) === '1')>Activo</option><option value="0" @selected((string) old('is_active', (int) $product->is_active) === '0')>Inactivo</option></select>@endif</div>
    </div>
    <h2>Características técnicas</h2>
    @include('catalog.products._attributes')
    @if(! $product->exists)
        @can('create', \App\Models\ProductLocation::class)<h2>Ubicaciones iniciales (opcional)</h2><div class="field"><label for="location_ids">Ubicaciones que permiten almacenar</label><select id="location_ids" name="location_ids[]" multiple>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(in_array((string) $location->id, array_map('strval', old('location_ids', [])), true))>{{ $paths[$location->id] }}</option>@endforeach</select><span class="help">Puedes seleccionar varias ubicaciones. Esta asociación no registra cantidades.</span></div>@endcan
    @endif
    <div class="actions"><button>Revisar producto y posibles duplicados</button><a href="{{ route('products.index') }}">Cancelar</a></div>
</form></section>
@endif
@endsection
