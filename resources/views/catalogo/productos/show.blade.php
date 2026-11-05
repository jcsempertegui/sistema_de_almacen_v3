@extends('layouts.app')
@section('title', $producto->codigo_interno)
@section('content')
@can('viewAny', \App\Models\UnidadIndividual::class)<p><a href="{{ route('unidades-individuales.index', ['producto_id' => $producto->id]) }}">Unidades individualizadas del producto</a></p>@endcan
<h1>{{ $producto->codigo_interno }} · {{ $producto->nombre }}</h1>
<section class="card"><p>{{ $producto->descripcion }}</p><dl><dt>Categoría</dt><dd>{{ $producto->categoria->nombre }}</dd><dt>Unidad</dt><dd>{{ $producto->unidadMedida->nombre }} ({{ $producto->unidadMedida->simbolo }})</dd><dt>Marca y referencia</dt><dd>{{ $producto->marca ?: 'Sin marca' }} · {{ $producto->referencia_fabricante ?: 'Sin referencia' }}</dd><dt>Control</dt><dd>{{ \App\Models\Producto::MODES[$producto->modo_control] }}{{ $producto->prestable ? ' · Prestable' : '' }}</dd><dt>Estado</dt><dd>{{ $producto->activo ? 'Activo' : 'Inactivo' }}</dd></dl>
<div class="actions">@can('update', $producto)<a href="{{ route('productos.edit', $producto) }}">Editar producto</a>@endcan @can('viewAny', \App\Models\UbicacionProducto::class)<a href="{{ route('ubicaciones-producto.index', $producto) }}">Ubicaciones del producto</a>@endcan @can('create', \App\Models\UbicacionProducto::class)<a href="{{ route('ubicaciones-producto.create', $producto) }}">Asociar ubicación</a>@endcan <a href="{{ route('productos.index') }}">Volver al listado</a></div></section>
<section class="card"><h2>Características técnicas e historial</h2>@include('catalogo.productos._values-history')</section>
@endsection
