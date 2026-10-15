@extends('layouts.app')
@section('title', $product->internal_code)
@section('content')
<h1>{{ $product->internal_code }} · {{ $product->name }}</h1>
<section class="card"><p>{{ $product->description }}</p><dl><dt>Categoría</dt><dd>{{ $product->category->name }}</dd><dt>Unidad</dt><dd>{{ $product->measurementUnit->name }} ({{ $product->measurementUnit->symbol }})</dd><dt>Marca y referencia</dt><dd>{{ $product->brand ?: 'Sin marca' }} · {{ $product->manufacturer_reference ?: 'Sin referencia' }}</dd><dt>Control</dt><dd>{{ \App\Models\Product::MODES[$product->control_mode] }}{{ $product->is_loanable ? ' · Prestable' : '' }}</dd><dt>Estado</dt><dd>{{ $product->is_active ? 'Activo' : 'Inactivo' }}</dd></dl>
<div class="actions">@can('update', $product)<a href="{{ route('products.edit', $product) }}">Editar producto</a>@endcan @can('viewAny', \App\Models\ProductLocation::class)<a href="{{ route('product-locations.index', $product) }}">Ubicaciones del producto</a>@endcan @can('create', \App\Models\ProductLocation::class)<a href="{{ route('product-locations.create', $product) }}">Asociar ubicación</a>@endcan <a href="{{ route('products.index') }}">Volver al listado</a></div></section>
<section class="card"><h2>Características técnicas e historial</h2>@include('catalog.products._values-history')</section>
@endsection
