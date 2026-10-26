@extends('layouts.app')
@section('title', 'Panel')
@section('content')
<div class="page-intro"><div><h1>Panel de inicio</h1><p>Tu punto de partida para la gestión del almacén.</p></div><span class="site-badge"><x-icon name="location" /> Río Grande</span></div>
<section class="dashboard-hero"><div><span class="eyebrow">CENTRO DE CONTROL DEL ALMACÉN</span><h2>Bienvenido, {{ auth()->user()->name }}</h2><p>Consulta tus materiales, gestiona documentos y sigue cada operación. Selecciona una opción del menú para comenzar.</p></div><div class="hero-actions">@can('stock.view')<a class="button" href="{{ route('stock.index') }}">Ver existencias <x-icon name="arrow" /></a>@endcan @can('viewAny', \App\Models\Product::class)<a class="button secondary" href="{{ route('products.index') }}">Catálogo</a>@endcan</div></section>
<p class="role">Rol: <strong>{{ auth()->user()->role->name }}</strong></p>
@include('dashboard.charts', ['charts' => $charts ?? ['movements' => null, 'loans' => null]])
@if(\Illuminate\Support\Facades\Gate::any(['stock.view', 'deliveries.view', 'loans.view', 'returns.view']) || \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\InventoryDocument::class))
<section aria-labelledby="daily-heading">
    <div class="section-heading"><h2 id="daily-heading">Operación diaria</h2><p>Accede directamente a los documentos y consultas que necesitas.</p></div>
    <div class="quick-access">
        @can('viewAny', \App\Models\InventoryDocument::class)<a class="quick-card" href="{{ route('inventory-documents.index') }}"><x-icon name="document" /><span><strong>Remitos y aperturas</strong><small>Ingresos y egresos</small></span></a>@endcan
        @can('deliveries.view')<a class="quick-card" href="{{ route('worker-operations.index', 'deliveries') }}"><x-icon name="transfer" /><span><strong>Entregas</strong><small>Materiales a trabajadores</small></span></a>@endcan
        @can('loans.view')<a class="quick-card" href="{{ route('worker-operations.index', 'loans') }}"><x-icon name="tool" /><span><strong>Préstamos</strong><small>Custodia de herramientas</small></span></a>@endcan
        @can('returns.view')<a class="quick-card" href="{{ route('worker-operations.index', 'returns') }}"><x-icon name="history" /><span><strong>Devoluciones</strong><small>Retorno al almacén</small></span></a>@endcan
    </div>
</section>
@endif
<div class="section-heading"><h2>Accesos disponibles</h2><p>Catálogo, organización y administración según tus permisos.</p></div>
<div class="cards">
    @can('viewAny', \App\Models\IndividualUnit::class)<section class="card"><span class="module-icon"><x-icon name="tool" /></span><h2>Unidades individualizadas</h2><p>Consulta las identidades de herramientas y equipos.</p><a href="{{ route('individual-units.index') }}">Consultar unidades individualizadas <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Product::class)<section class="card"><span class="module-icon"><x-icon name="box" /></span><h2>Productos</h2><p>Consulta el catálogo, sus características técnicas e historial.</p><a href="{{ route('products.index') }}">Consultar productos <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Location::class)<section class="card"><span class="module-icon"><x-icon name="location" /></span><h2>Ubicaciones</h2><p>Consulta sectores, estantes, gabinetes y su jerarquía.</p><a href="{{ route('locations.index') }}">Consultar ubicaciones <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\CatalogAttribute::class)<section class="card"><span class="module-icon"><x-icon name="sliders" /></span><h2>Atributos técnicos</h2><p>Configura las definiciones técnicas y sus tipos de dato.</p><a href="{{ route('attributes.index') }}">Consultar atributos técnicos <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Category::class)<section class="card"><span class="module-icon"><x-icon name="tag" /></span><h2>Categorías</h2><p>Organiza las categorías del almacén.</p><a href="{{ route('categories.index') }}">Consultar categorías <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\MeasurementUnit::class)<section class="card"><span class="module-icon"><x-icon name="sliders" /></span><h2>Unidades de medida</h2><p>Consulta unidades, símbolos y precisión decimal.</p><a href="{{ route('measurement-units.index') }}">Consultar unidades de medida <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Role::class)<section class="card"><span class="module-icon"><x-icon name="shield" /></span><h2>Roles y permisos</h2><p>Configura las capacidades de acceso de cada rol.</p><a href="{{ route('roles.index') }}">Gestionar roles <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\AdministrativeAuditLog::class)<section class="card"><span class="module-icon"><x-icon name="history" /></span><h2>Auditoría</h2><p>Consulta los cambios de usuarios, roles y permisos.</p><a href="{{ route('audit.index') }}">Consultar auditoría <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Worker::class)<section class="card"><span class="module-icon"><x-icon name="users" /></span><h2>Trabajadores</h2><p>Consulta las personas registradas para el almacén.</p><a href="{{ route('workers.index') }}">Consultar trabajadores <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\User::class)<section class="card"><span class="module-icon"><x-icon name="users" /></span><h2>Usuarios</h2><p>Administra las cuentas y sus permisos de acceso.</p><a href="{{ route('users.index') }}">Gestionar usuarios <x-icon name="arrow" /></a></section>@endcan
</div>
@endsection
