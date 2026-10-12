@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
<section class="card login-card">
    <h1>Iniciar sesión</h1>
    <p>Ingresa con tu cuenta para acceder al sistema de gestión de almacén.</p>
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="username" required autofocus>
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" maxlength="255" autocomplete="current-password" required>
        <button type="submit">Ingresar</button>
    </form>
</section>
@endsection
