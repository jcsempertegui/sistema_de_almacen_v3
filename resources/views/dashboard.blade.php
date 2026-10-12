@extends('layouts.app')
@section('title', 'Panel')
@section('content')
<h1>Panel de inicio</h1>
<section class="card"><h2>Bienvenido, {{ auth()->user()->name }}</h2><p>Selecciona una opción del menú para comenzar.</p></section>
<p class="role">Rol: <strong>{{ auth()->user()->role->name }}</strong></p>
<div class="cards">
    @can('viewAny', \App\Models\Worker::class)<section class="card"><h2>Trabajadores</h2><p>Consulta las personas registradas para el almacén.</p><a href="{{ route('workers.index') }}">Consultar trabajadores</a></section>@endcan
    @can('viewAny', \App\Models\User::class)<section class="card"><h2>Usuarios</h2><p>Administra las cuentas y sus permisos de acceso.</p><a href="{{ route('users.index') }}">Gestionar usuarios</a></section>@endcan
</div>
@endsection
