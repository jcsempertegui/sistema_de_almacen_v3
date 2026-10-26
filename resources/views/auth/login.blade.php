@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('body-class', 'login-page')
@section('content')
<div class="login-shell">
    <section class="login-intro" aria-label="SGA CONFIPETROL">
        <div class="login-brand"><span class="logo-tile"><img src="{{ asset('images/confipetrol.png') }}" width="54" height="54" alt=""></span><strong>CONFIPETROL</strong></div>
        <div class="login-intro-copy"><span class="eyebrow">PLANTA RÍO GRANDE</span><h2>Tu almacén. <br>Cada operación, <br>bajo control.</h2><p>Gestiona materiales, documentos y herramientas desde un mismo lugar.</p></div>
        <div class="login-features"><span><x-icon name="box" /> Existencias por ubicación</span><span><x-icon name="tool" /> Trazabilidad de herramientas</span><span><x-icon name="shield" /> Acceso según tus permisos</span></div>
        <div class="login-intro-footer">Sistema de Gestión de Almacén</div>
    </section>
    <section class="login-form-panel">
        <span class="login-kicker">BIENVENIDO DE NUEVO</span>
        <h1>Iniciar sesión</h1>
        <p>Ingresa con tu cuenta para acceder al sistema de gestión de almacén.</p>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="username" placeholder="tu.correo@empresa.com" required autofocus>
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" maxlength="255" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
            <button type="submit" class="login-submit">Ingresar <x-icon name="arrow" /></button>
        </form>
        <div class="login-help"><x-icon name="shield" /><span>Si necesitas una cuenta o ayuda para ingresar, contacta al administrador del sistema.</span></div>
        <div class="login-location">Planta Separadora de Líquidos Río Grande</div>
    </section>
</div>
@endsection
