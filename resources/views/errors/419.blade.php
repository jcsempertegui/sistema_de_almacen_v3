@extends('layouts.app')
@section('title', 'Solicitud no válida o caducada')
@section('content')
<section class="card">
    <h1>Solicitud no válida o caducada</h1>
    <p>No se pudo validar la solicitud. Vuelve a abrir el formulario e inténtalo nuevamente. Si tu sesión terminó, inicia sesión otra vez.</p>
    <p class="help">Código de respuesta: 419.</p>
    <div class="actions"><a href="{{ url('/') }}">Volver al inicio</a></div>
</section>
@endsection
