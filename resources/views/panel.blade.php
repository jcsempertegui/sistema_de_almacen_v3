@extends('layouts.app')
@section('title', 'Panel')
@section('content')
<div class="page-intro"><div><h1>Panel de operaciones</h1><p class="dashboard-date">{{ $operations['date'] }} · Hora de Bolivia</p></div><span class="site-badge"><x-icon name="ubicacion" /> Río Grande</span></div>
<section aria-labelledby="daily-heading">
    <div class="section-heading"><h2 id="daily-heading">¿Qué necesitas hacer hoy?</h2><p>Prepara el documento y confirma después de realizar la operación física.</p></div>
    <div class="quick-access daily-primary">
        @can('deliveries.create')<a class="quick-card" href="{{ route('operaciones-trabajadores.create', 'deliveries') }}"><x-icon name="transfer" /><span><strong>Entregar materiales</strong><small>EPP y consumibles para un trabajador</small></span></a>@endcan
        @can('loans.create')<a class="quick-card" href="{{ route('operaciones-trabajadores.create', 'loans') }}"><x-icon name="tool" /><span><strong>Prestar herramientas</strong><small>Identifica cada herramienta y su responsable</small></span></a>@endcan
        @can('returns.create')<a class="quick-card" href="{{ route('operaciones-trabajadores.create', 'returns') }}"><x-icon name="history" /><span><strong>Recibir herramientas</strong><small>Registra una devolución parcial o total</small></span></a>@endcan
        @can('stock.view')<a class="quick-card" href="{{ route('stock.index') }}"><x-icon name="box" /><span><strong>Consultar existencias</strong><small>Revisa cantidades y ubicaciones</small></span></a>@endcan
    </div>
    <div class="actions daily-queries">
        @can('deliveries.view')<a href="{{ route('operaciones-trabajadores.index', 'deliveries') }}">Consultar entregas</a>@endcan
        @can('loans.view')<a href="{{ route('operaciones-trabajadores.index', ['clase' => 'loans', 'pending' => 1]) }}">Herramientas pendientes</a>@endcan
        @can('returns.view')<a href="{{ route('operaciones-trabajadores.index', 'returns') }}">Consultar devoluciones</a>@endcan
        @can('viewAny', \App\Models\DocumentoInventario::class)<a href="{{ route('documentos-inventario.index') }}">Consultar remitos</a>@endcan
    </div>
</section>
@include('panel.operaciones')
@include('panel.graficas', ['charts' => $charts ?? ['movements' => null, 'loans' => null]])
<details class="dashboard-modules"><summary>Catálogo y administración <span>Ver accesos disponibles</span></summary>
<div class="cards">
    @can('viewAny', \App\Models\UnidadIndividual::class)<section class="card"><span class="module-icon"><x-icon name="tool" /></span><h2>Unidades individualizadas</h2><p>Consulta las identidades de herramientas y equipos.</p><a href="{{ route('unidades-individuales.index') }}">Consultar unidades individualizadas <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Producto::class)<section class="card"><span class="module-icon"><x-icon name="box" /></span><h2>Productos</h2><p>Consulta el catálogo, sus características técnicas e historial.</p><a href="{{ route('productos.index') }}">Consultar productos <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Ubicacion::class)<section class="card"><span class="module-icon"><x-icon name="ubicacion" /></span><h2>Ubicaciones</h2><p>Consulta sectores, estantes, gabinetes y su jerarquía.</p><a href="{{ route('ubicaciones.index') }}">Consultar ubicaciones <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\AtributoCatalogo::class)<section class="card"><span class="module-icon"><x-icon name="sliders" /></span><h2>Atributos técnicos</h2><p>Configura las definiciones técnicas y sus tipos de dato.</p><a href="{{ route('atributos.index') }}">Consultar atributos técnicos <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Categoria::class)<section class="card"><span class="module-icon"><x-icon name="tag" /></span><h2>Categorías</h2><p>Organiza las categorías del almacén.</p><a href="{{ route('categorias.index') }}">Consultar categorías <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\UnidadMedida::class)<section class="card"><span class="module-icon"><x-icon name="sliders" /></span><h2>Unidades de medida</h2><p>Consulta unidades, símbolos y precisión decimal.</p><a href="{{ route('unidades-medida.index') }}">Consultar unidades de medida <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Rol::class)<section class="card"><span class="module-icon"><x-icon name="shield" /></span><h2>Roles y permisos</h2><p>Configura las capacidades de acceso de cada rol.</p><a href="{{ route('roles.index') }}">Gestionar roles <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\RegistroAuditoriaAdministrativa::class)<section class="card"><span class="module-icon"><x-icon name="history" /></span><h2>Auditoría</h2><p>Consulta los cambios de usuarios, roles y permisos.</p><a href="{{ route('audit.index') }}">Consultar auditoría <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Trabajador::class)<section class="card"><span class="module-icon"><x-icon name="usuarios" /></span><h2>Trabajadores</h2><p>Consulta las personas registradas para el almacén.</p><a href="{{ route('trabajadores.index') }}">Consultar trabajadores <x-icon name="arrow" /></a></section>@endcan
    @can('viewAny', \App\Models\Usuario::class)<section class="card"><span class="module-icon"><x-icon name="usuarios" /></span><h2>Usuarios</h2><p>Administra las cuentas y sus permisos de acceso.</p><a href="{{ route('usuarios.index') }}">Gestionar usuarios <x-icon name="arrow" /></a></section>@endcan
</div>
</details>
@endsection
