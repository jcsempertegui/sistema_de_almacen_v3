@extends('layouts.app')
@section('title', 'El registro cambió')
@section('content')
<section class="card"><h1>El registro cambió</h1><p>Otra operación actualizó el producto. Recarga el formulario y revisa los datos antes de volver a guardar.</p>
@if(request()->route('product') instanceof \App\Models\Product)
    @can('update', request()->route('product'))<a href="{{ route('products.edit', request()->route('product')) }}">Recargar formulario</a>@else<a href="{{ route('products.index') }}">Volver al listado</a>@endcan
@else<a href="{{ route('dashboard') }}">Volver al panel</a>@endif
</section>
@endsection
