@extends('layouts.app')
@section('title', $producto->exists ? 'Editar producto' : 'Nuevo producto')
@section('content')
<h1>{{ $producto->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>
<section class="card"><form method="GET" action="{{ $producto->exists ? route('productos.edit', $producto) : route('productos.create') }}">
    <div class="field"><label for="choose_category">Selecciona la categoría antes de completar el formulario</label><select id="choose_category" name="categoria_id" required><option value="">Seleccionar</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected($categoryId === $categoria->id)>{{ $categoria->nombre }}{{ ! $categoria->activo ? ' · Inactiva' : '' }}</option>@endforeach</select></div><button>Cargar atributos de la categoría</button>
    <p class="help">Cambiar esta selección recarga el formulario. Los cambios aún no guardados deberán ingresarse nuevamente.</p>
</form></section>
@if($categoryId)
<section class="card"><form method="POST" action="{{ $producto->exists ? route('productos.preview-update', $producto) : route('productos.preview') }}">
    @csrf
    <input type="hidden" name="categoria_id" value="{{ $categoryId }}">
    @if($producto->exists)<input type="hidden" name="version_bloqueo" value="{{ old('version_bloqueo', $producto->version_bloqueo) }}">@endif
    <div class="form-grid">
        <div class="field"><label for="codigo_interno">Código interno</label><input id="codigo_interno" name="codigo_interno" value="{{ old('codigo_interno', $producto->codigo_interno) }}" maxlength="60" required @readonly($producto->exists)><span class="help">El código se conserva y no puede reutilizarse.</span></div>
        <div class="field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre', $producto->nombre) }}" maxlength="150" required></div>
        <div class="field"><label for="unidad_medida_id">Unidad de medida</label><select id="unidad_medida_id" name="unidad_medida_id" required><option value="">Seleccionar</option>@foreach($unidades as $unidad)<option value="{{ $unidad->id }}" @selected((string) old('unidad_medida_id', $producto->unidad_medida_id) === (string) $unidad->id)>{{ $unidad->nombre }} ({{ $unidad->simbolo }}){{ ! $unidad->activo ? ' · Inactiva' : '' }}</option>@endforeach</select></div>
        <div class="field"><label for="modo_control">Modo de control</label><select id="modo_control" name="modo_control">@foreach(\App\Models\Producto::MODES as $key => $label)<option value="{{ $key }}" @selected(old('modo_control', $producto->modo_control ?? 'quantity') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="prestable">Prestable</label><select id="prestable" name="prestable"><option value="0" @selected((string) old('prestable', (int) $producto->prestable) === '0')>No</option><option value="1" @selected((string) old('prestable', (int) $producto->prestable) === '1')>Sí</option></select><span class="help">Un producto prestable requiere control individual.</span></div>
        <div class="field"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" maxlength="10000">{{ old('descripcion', $producto->descripcion) }}</textarea></div>
        <div class="field"><label for="activo">Estado</label>@if($producto->exists && ! auth()->user()->can('changeStatus', $producto))<input value="{{ $producto->activo ? 'Activo' : 'Inactivo' }}" disabled><input type="hidden" name="activo" value="{{ (int) $producto->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $producto->activo) === '1')>Activo</option><option value="0" @selected((string) old('activo', (int) $producto->activo) === '0')>Inactivo</option></select>@endif</div>
    </div>
    <details class="simple-details" @if(old('marca', $producto->marca) || old('referencia_fabricante', $producto->referencia_fabricante) || $errors->has('marca') || $errors->has('referencia_fabricante')) open @endif>
        <summary>Marca y referencia (opcional)</summary><div class="form-grid">
        <div class="field"><label for="marca">Marca</label><input id="marca" name="marca" value="{{ old('marca', $producto->marca) }}" maxlength="100"></div>
        <div class="field"><label for="referencia_fabricante">Referencia de fabricante</label><input id="referencia_fabricante" name="referencia_fabricante" value="{{ old('referencia_fabricante', $producto->referencia_fabricante) }}" maxlength="100"></div>
        </div>
    </details>
    <details class="simple-details" @if($definitions->contains('obligatorio', true) || $errors->has('values') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'values.'))) open @endif><summary>Características técnicas de la categoría</summary>
    @include('catalogo.productos._attributes')
    </details>
    @if(! $producto->exists)
        @can('create', \App\Models\UbicacionProducto::class)<h2>Ubicaciones iniciales (opcional)</h2><div class="field"><label for="location_ids">Ubicaciones que permiten almacenar</label><select id="location_ids" name="location_ids[]" multiple>@foreach($ubicaciones as $ubicacion)<option value="{{ $ubicacion->id }}" @selected(in_array((string) $ubicacion->id, array_map('strval', old('location_ids', [])), true))>{{ $paths[$ubicacion->id] }}</option>@endforeach</select><span class="help">Puedes seleccionar varias ubicaciones. Esta asociación no registra cantidades.</span></div>@endcan
    @endif
    <div class="actions"><button>Revisar producto y posibles duplicados</button><a href="{{ route('productos.index') }}">Cancelar</a></div>
</form></section>
@endif
@endsection
