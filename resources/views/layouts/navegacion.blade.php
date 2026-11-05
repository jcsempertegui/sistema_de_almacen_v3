@php
    $item = static fn ($label, $route, $ability, $subject = [], $icon = 'grid', $parameters = [], $match = null) => compact('label', 'route', 'ability', 'subject', 'icon', 'parameters', 'match');
    $groups = [
        'Inicio' => [$item('Inicio', 'dashboard', 'access-panel')],
        'Existencias' => [$item('Existencias', 'stock.index', 'stock.view', [], 'box')],
        'Entregas' => [$item('Entregas', 'operaciones-trabajadores.index', 'deliveries.view', [], 'transfer', ['clase' => 'deliveries'], 'operaciones-trabajadores.*')],
        'Herramientas' => [
            $item('Herramientas prestadas', 'operaciones-trabajadores.index', 'loans.view', [], 'tool', ['clase' => 'loans'], 'operaciones-trabajadores.*'),
            $item('Devoluciones', 'operaciones-trabajadores.index', 'returns.view', [], 'history', ['clase' => 'returns'], 'operaciones-trabajadores.*'),
            $item('Disponibilidad', 'availability.index', 'availability.view', [], 'check'),
            $item('Unidades individualizadas', 'unidades-individuales.index', 'viewAny', \App\Models\UnidadIndividual::class, 'tool', [], 'unidades-individuales.*'),
        ],
        'Remitos' => [$item('Remitos', 'documentos-inventario.index', 'viewAny', \App\Models\DocumentoInventario::class, 'documento', [], 'documentos-inventario.*')],
        'Catálogo' => [
            $item('Productos', 'productos.index', 'viewAny', \App\Models\Producto::class, 'box', [], 'productos.*'),
            $item('Categorías', 'categorias.index', 'viewAny', \App\Models\Categoria::class, 'tag', [], 'categorias.*'),
            $item('Atributos técnicos', 'atributos.index', 'viewAny', \App\Models\AtributoCatalogo::class, 'sliders', [], 'atributos.*'),
            $item('Unidades de medida', 'unidades-medida.index', 'viewAny', \App\Models\UnidadMedida::class, 'sliders', [], 'unidades-medida.*'),
            $item('Ubicaciones', 'ubicaciones.index', 'viewAny', \App\Models\Ubicacion::class, 'ubicacion', [], 'ubicaciones.*'),
        ],
        'Trabajadores' => [$item('Trabajadores', 'trabajadores.index', 'viewAny', \App\Models\Trabajador::class, 'usuarios', [], 'trabajadores.*')],
        'Reportes' => [],
        'Administración' => [
            $item('Corregir diferencia de inventario', 'corrections.index', 'adjustments.view', [], 'sliders', ['clase' => 'adjustment'], 'corrections.*'),
            $item('Compensar operación confirmada', 'corrections.index', 'compensations.view', [], 'history', ['clase' => 'compensation'], 'corrections.*'),
            $item('Usuarios', 'usuarios.index', 'viewAny', \App\Models\Usuario::class, 'usuarios', [], 'usuarios.*'),
            $item('Roles y permisos', 'roles.index', 'viewAny', \App\Models\Rol::class, 'shield', [], 'roles.*'),
            $item('Auditoría', 'audit.index', 'viewAny', \App\Models\RegistroAuditoriaAdministrativa::class, 'history'),
            $item('Respaldos', 'backups.index', 'backups.view', [], 'database'),
            $item('Existencias iniciales', 'documentos-inventario.create', 'initial_stock.create', [], 'documento', ['tipo' => 'opening']),
            $item('Importar existencias iniciales', 'importacion-existencias-iniciales.create', 'initial_stock.import', [], 'documento'),
        ],
    ];
    foreach (app(\App\Services\CatalogoReportes::class)->navigation() as $group) {
        $entry = $item($group['label'], 'reportes-control.index', app(\App\Services\ReportesControl::class)->permiso($group['first']), [], 'chart', ['clase' => $group['first']]);
        $entry['report_kinds'] = $group['kinds'];
        $groups['Reportes'][] = $entry;
    }
    $activeEntry = static function ($entry) {
        if (isset($entry['report_kinds'])) return request()->routeIs('reportes-control.index') && in_array(request()->route('clase'), $entry['report_kinds'], true);
        if ($entry['route'] === 'documentos-inventario.create') return false;
        if ($entry['route'] === 'stock.index' && request()->routeIs('movements.*')) return true;
        if ($entry['route'] === 'productos.index' && request()->routeIs('ubicaciones-producto.*')) return true;
        if ($entry['route'] === 'categorias.index' && request()->routeIs('atributos-categoria.*')) return true;
        return request()->routeIs($entry['match'] ?? $entry['route']) && (!isset($entry['parameters']['clase']) || request()->route('clase') === $entry['parameters']['clase']);
    };
@endphp
<nav class="sidebar-nav simplified-nav" aria-label="Navegación principal">
    @foreach($groups as $heading => $items)
        @php
            $visible = array_filter($items, static fn ($entry) => \Illuminate\Support\Facades\Gate::allows($entry['ability'], $entry['subject']));
            $expandable = in_array($heading, ['Herramientas', 'Catálogo', 'Reportes', 'Administración']);
        @endphp
        @if($visible)
            <section class="nav-group" aria-label="{{ $heading }}">
                @if($expandable)<details class="nav-disclosure" @if(count(array_filter($visible, $activeEntry))) open @endif><summary><x-icon :name="$heading === 'Herramientas' ? 'tool' : ($heading === 'Catálogo' ? 'box' : ($heading === 'Reportes' ? 'chart' : 'shield'))" /><span>{{ $heading }}</span></summary><div class="nav-children">@endif
                @foreach($visible as $entry)
                    @php($active = $activeEntry($entry))
                    <a href="{{ route($entry['route'], $entry['parameters']) }}" @class(['nav-link', 'is-active' => $active]) @if($active) aria-current="page" @endif><x-icon :name="$entry['icon']" /><span>{{ $entry['label'] }}</span></a>
                @endforeach
                @if($expandable)</div></details>@endif
            </section>
        @endif
    @endforeach
</nav>
