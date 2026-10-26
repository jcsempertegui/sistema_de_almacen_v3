@php
    // Presentation only: use the same Gates/Policies as the original navigation.
    $item = static fn ($label, $route, $ability, $subject = [], $icon = 'grid', $parameters = [], $match = null) => compact('label', 'route', 'ability', 'subject', 'icon', 'parameters', 'match');
    $groups = [
        'General' => [$item('Panel', 'dashboard', 'access-panel')],
        'Catálogo' => [
            $item('Productos', 'products.index', 'viewAny', \App\Models\Product::class, 'box', [], 'products.*'),
            $item('Categorías', 'categories.index', 'viewAny', \App\Models\Category::class, 'tag', [], 'categories.*'),
            $item('Atributos técnicos', 'attributes.index', 'viewAny', \App\Models\CatalogAttribute::class, 'sliders', [], 'attributes.*'),
            $item('Unidades de medida', 'measurement-units.index', 'viewAny', \App\Models\MeasurementUnit::class, 'sliders', [], 'measurement-units.*'),
            $item('Ubicaciones', 'locations.index', 'viewAny', \App\Models\Location::class, 'location', [], 'locations.*'),
            $item('Unidades individualizadas', 'individual-units.index', 'viewAny', \App\Models\IndividualUnit::class, 'tool', [], 'individual-units.*'),
        ],
        'Operación del almacén' => [
            $item('Remitos y aperturas', 'inventory-documents.index', 'viewAny', \App\Models\InventoryDocument::class, 'document', [], 'inventory-documents.*'),
            $item('Existencias', 'stock.index', 'stock.view', [], 'box'),
            $item('Movimientos', 'movements.index', 'movements.view', [], 'transfer'),
            $item('Disponibilidad', 'availability.index', 'availability.view', [], 'check'),
        ],
        'Personas y herramientas' => [$item('Trabajadores', 'workers.index', 'viewAny', \App\Models\Worker::class, 'users', [], 'workers.*')],
        'Control' => [
            $item('Ajustes', 'corrections.index', 'adjustments.view', [], 'sliders', ['kind' => 'adjustment'], 'corrections.*'),
            $item('Compensaciones', 'corrections.index', 'compensations.view', [], 'history', ['kind' => 'compensation'], 'corrections.*'),
        ],
        'Reportes' => [],
        'Administración' => [
            $item('Usuarios', 'users.index', 'viewAny', \App\Models\User::class, 'users', [], 'users.*'),
            $item('Roles y permisos', 'roles.index', 'viewAny', \App\Models\Role::class, 'shield', [], 'roles.*'),
            $item('Auditoría', 'audit.index', 'viewAny', \App\Models\AdministrativeAuditLog::class, 'history'),
            $item('Respaldos', 'backups.index', 'backups.view', [], 'database'),
        ],
    ];
    foreach (\App\Support\WorkerOperationTypes::LABELS as $kind => $label) {
        $groups['Personas y herramientas'][] = $item($label, 'worker-operations.index', $kind.'.view', [], 'transfer', ['kind' => $kind], 'worker-operations.*');
    }
    foreach (\App\Services\ControlReports::LABELS as $kind => $label) {
        $groups['Reportes'][] = $item('Reporte: '.$label, 'control-reports.index', app(\App\Services\ControlReports::class)->permission($kind), [], 'chart', ['kind' => $kind]);
    }
@endphp
<nav class="sidebar-nav" aria-label="Navegación principal">
    @foreach($groups as $heading => $items)
        @php($visible = array_filter($items, static fn ($entry) => \Illuminate\Support\Facades\Gate::allows($entry['ability'], $entry['subject'])))
        @if($visible)
            <section class="nav-group" aria-label="{{ $heading }}">
                <h2>{{ $heading }}</h2>
                @foreach($visible as $entry)
                    @php($active = request()->routeIs($entry['match'] ?? $entry['route']) && (!isset($entry['parameters']['kind']) || request()->route('kind') === $entry['parameters']['kind']))
                    <a href="{{ route($entry['route'], $entry['parameters']) }}" @class(['nav-link', 'is-active' => $active]) @if($active) aria-current="page" @endif><x-icon :name="$entry['icon']" /><span>{{ $entry['label'] }}</span></a>
                @endforeach
            </section>
        @endif
    @endforeach
</nav>
