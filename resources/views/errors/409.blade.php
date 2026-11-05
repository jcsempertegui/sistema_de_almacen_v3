@extends('layouts.app')
@section('title', 'El registro cambió')
@section('content')
<section class="card"><h1>El registro cambió</h1><p>Otra operación actualizó el producto. Recarga el formulario y revisa los datos antes de volver a guardar.</p>
@if(request()->route('producto') instanceof \App\Models\Producto)
    @can('update', request()->route('producto'))<a href="{{ route('productos.edit', request()->route('producto')) }}">Recargar formulario</a>@else<a href="{{ route('productos.index') }}">Volver al listado</a>@endcan
@else<a href="{{ route('dashboard') }}">Volver al panel</a>@endif
</section>
@endsection
