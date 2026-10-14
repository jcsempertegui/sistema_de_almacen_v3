@extends('layouts.app')
@section('title', 'Panel')
@section('content')
<h1>Panel de inicio</h1>
<section class="card"><h2>Bienvenido, {{ auth()->user()->name }}</h2><p>Selecciona una opción del menú para comenzar.</p></section>
<p class="role">Rol: <strong>{{ auth()->user()->role->name }}</strong></p>
<div class="cards">
    @can('viewAny', \App\Models\Category::class)<section class="card"><h2>Categorías</h2><p>Organiza las categorías del almacén.</p><a href="{{ route('categories.index') }}">Consultar categorías</a></section>@endcan
    @can('viewAny', \App\Models\MeasurementUnit::class)<section class="card"><h2>Unidades de medida</h2><p>Consulta unidades, símbolos y precisión decimal.</p><a href="{{ route('measurement-units.index') }}">Consultar unidades de medida</a></section>@endcan
    @can('viewAny', \App\Models\Role::class)<section class="card"><h2>Roles y permisos</h2><p>Configura las capacidades de acceso de cada rol.</p><a href="{{ route('roles.index') }}">Gestionar roles</a></section>@endcan
    @can('viewAny', \App\Models\AdministrativeAuditLog::class)<section class="card"><h2>Auditoría</h2><p>Consulta los cambios de usuarios, roles y permisos.</p><a href="{{ route('audit.index') }}">Consultar auditoría</a></section>@endcan
    @can('viewAny', \App\Models\Worker::class)<section class="card"><h2>Trabajadores</h2><p>Consulta las personas registradas para el almacén.</p><a href="{{ route('workers.index') }}">Consultar trabajadores</a></section>@endcan
    @can('viewAny', \App\Models\User::class)<section class="card"><h2>Usuarios</h2><p>Administra las cuentas y sus permisos de acceso.</p><a href="{{ route('users.index') }}">Gestionar usuarios</a></section>@endcan
</div>
@endsection
