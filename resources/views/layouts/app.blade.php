<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel') · SGA CONFIPETROL</title>
    <link rel="stylesheet" href="{{ asset('css/sga.css') }}">
</head>
<body>
<header class="site-header">
    <div class="brand"><strong>SGA CONFIPETROL</strong><span>Planta Separadora de Líquidos Río Grande</span></div>
    @auth
        <nav aria-label="Navegación principal">
            @can('access-panel')<a href="{{ route('dashboard') }}">Panel</a>@endcan
            @can('viewAny', \App\Models\Role::class)<a href="{{ route('roles.index') }}">Roles y permisos</a>@endcan
            @can('viewAny', \App\Models\AdministrativeAuditLog::class)<a href="{{ route('audit.index') }}">Auditoría</a>@endcan
            @can('viewAny', \App\Models\User::class)<a href="{{ route('users.index') }}">Usuarios</a>@endcan
            @can('viewAny', \App\Models\Worker::class)<a href="{{ route('workers.index') }}">Trabajadores</a>@endcan
            @can('viewAny', \App\Models\Category::class)<a href="{{ route('categories.index') }}">Categorías</a>@endcan
            @can('viewAny', \App\Models\MeasurementUnit::class)<a href="{{ route('measurement-units.index') }}">Unidades de medida</a>@endcan
            @can('viewAny', \App\Models\CatalogAttribute::class)<a href="{{ route('attributes.index') }}">Atributos técnicos</a>@endcan
            @can('viewAny', \App\Models\Location::class)<a href="{{ route('locations.index') }}">Ubicaciones</a>@endcan
            @can('viewAny', \App\Models\Product::class)<a href="{{ route('products.index') }}">Productos</a>@endcan
            @can('viewAny', \App\Models\IndividualUnit::class)<a href="{{ route('individual-units.index') }}">Unidades individualizadas</a>@endcan
            @can('viewAny', \App\Models\InventoryDocument::class)<a href="{{ route('inventory-documents.index') }}">Remitos y aperturas</a>@endcan
            @can('stock.view')<a href="{{ route('stock.index') }}">Existencias</a>@endcan
            @can('movements.view')<a href="{{ route('movements.index') }}">Movimientos</a>@endcan
            <span>{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">Cerrar sesión</button></form>
        </nav>
    @endauth
</header>
<main class="container">
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="notice error" role="alert"><strong>Revisa los siguientes datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
</body>
</html>
