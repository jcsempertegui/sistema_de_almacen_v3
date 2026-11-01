@extends('layouts.app')
@section('title', $label)
@section('body-class', 'reports-page')
@section('content')
<div class="page-intro report-page-intro"><div><span class="panel-eyebrow">INFORMES DEL ALMACÉN</span><h1>{{ $group['label'] }}</h1><p>Consulta el detalle, filtra la información y prepara tus informes.</p></div><span class="site-badge"><x-icon name="chart" /> Río Grande</span></div>
<nav class="report-tabs" aria-label="Consultas de este grupo">
    @foreach($group['kinds'] as $tab)
        @can(app(\App\Services\ControlReports::class)->permission($tab))
            <a href="{{ route('control-reports.index', $tab) }}" @if($kind === $tab) aria-current="page" @endif>{{ \App\Services\ControlReports::LABELS[$tab] }}</a>
        @endcan
    @endforeach
</nav>
@if(!$printing)
<section class="card report-filter-panel" aria-labelledby="report-filter-title">
    <div class="operational-heading"><h2 id="report-filter-title">Personaliza tu consulta</h2><span>Los filtros se aplican también al CSV y a la impresión.</span></div>
    <form method="GET" action="{{ route('control-reports.index', $kind) }}">
        <div class="report-filter-grid">
            <div class="field"><label for="report-q">Buscar</label><input id="report-q" type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Artículo, código, documento o persona"></div>
            @if(in_array('from', $availableFilters))<div class="field"><label for="report-from">Desde</label><input id="report-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>@endif
            @if(in_array('to', $availableFilters))<div class="field"><label for="report-to">{{ $kind === 'stock' ? 'Existencias al cierre de' : 'Hasta (inclusive)' }}</label><input id="report-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>@endif
            @if(in_array('product_id', $availableFilters))<div class="field"><label for="report-product">Artículo</label><select id="report-product" name="product_id"><option value="">Todos los artículos</option>@foreach($productChoices as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->internal_code }} · {{ $product->name }}</option>@endforeach</select></div>@endif
            @if(in_array('category_id', $availableFilters))<div class="field"><label for="report-category">Categoría</label><select id="report-category" name="category_id"><option value="">Todas las categorías</option>@foreach($categoryChoices as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>@endif
            @if(in_array('location_id', $availableFilters))<div class="field"><label for="report-location">Ubicación</label><select id="report-location" name="location_id"><option value="">Todas las ubicaciones</option>@foreach($locationChoices as $location)<option value="{{ $location->id }}" @selected(($filters['location_id'] ?? '') == $location->id)>{{ $location->code }} · {{ $location->name }}</option>@endforeach</select></div>@endif
            @if(in_array('worker_id', $availableFilters))<div class="field"><label for="report-worker">Trabajador responsable</label><select id="report-worker" name="worker_id"><option value="">Todos los trabajadores</option>@foreach($workerChoices as $worker)<option value="{{ $worker->id }}" @selected(($filters['worker_id'] ?? '') == $worker->id)>{{ $worker->codigo }} · {{ $worker->nombres }} {{ $worker->apellidos }}{{ $worker->is_active ? '' : ' · Inactivo' }}</option>@endforeach</select></div>@endif
            @if(in_array('status', $availableFilters))<div class="field"><label for="report-status">Estado documental</label><select id="report-status" name="status"><option value="">Todos los estados</option>@foreach($kind === 'corrections' ? ['draft', 'requested', 'authorized', 'rejected', 'cancelled', 'executed'] : ['draft', 'confirmed', 'cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('control.states.'.$status, [], 'es') }}</option>@endforeach</select></div>@endif
            @if(in_array('type', $availableFilters))<div class="field"><label for="report-type">Tipo de documento</label><select id="report-type" name="type"><option value="">Todos los tipos</option>@foreach($kind === 'corrections' ? ['adjustment', 'compensation'] : ['opening', 'receipt', 'issue'] as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __('control.kinds.'.$type, [], 'es') }}</option>@endforeach</select></div>@endif
            @if(in_array('custody', $availableFilters))<div class="field"><label for="report-custody">Responsabilidad actual</label><select id="report-custody" name="custody"><option value="">Todas</option><option value="pending" @selected(($filters['custody'] ?? '') === 'pending')>Pendientes, incluidos vencidos</option><option value="overdue" @selected(($filters['custody'] ?? '') === 'overdue')>Solo vencidos</option>@if($kind === 'loans')<option value="returned" @selected(($filters['custody'] ?? '') === 'returned')>Devueltos</option>@endif</select></div>@endif
        </div>
        <div class="report-filter-actions"><button type="submit"><x-icon name="chart" /> Consultar informe</button><a class="button secondary" href="{{ route('control-reports.index', $kind) }}">Limpiar filtros</a>
        @if(in_array('from', $availableFilters))<a href="{{ route('control-reports.index', ['kind' => $kind, 'from' => now('America/La_Paz')->startOfMonth()->toDateString(), 'to' => now('America/La_Paz')->toDateString()]) }}">Este mes</a>@endif
        @if($kind === 'deliveries')<a href="{{ route('control-reports.index', ['kind' => $kind, 'q' => 'Dotación semestral']) }}">Buscar dotaciones semestrales</a>@endif
        </div>
    </form>
</section>
@endif
<div class="report-summary" aria-label="Resumen del resultado">
    @foreach($summary as $metric => $value)<div><span>{{ $metric }}</span><strong>{{ number_format($value, 0, ',', '.') }}</strong></div>@endforeach
</div>
<section class="card report-result" aria-labelledby="report-result-title">
    <header class="report-result-header"><div><span class="panel-eyebrow">CONFIPETROL · PLANTA RÍO GRANDE</span><h2 id="report-result-title">{{ $label }}</h2><p>Generado el {{ $generatedAt }} · Hora de Bolivia · {{ auth()->user()->name }}</p></div><div class="report-export-actions">
        @if($printing)<button type="button" onclick="window.print()">Imprimir / Guardar PDF</button><a class="button secondary" href="{{ route('control-reports.index', ['kind' => $kind] + $filters) }}">Volver al informe</a>
        @else
            @can(app(\App\Services\ControlReports::class)->permission($kind, 'export'))<a class="button secondary" href="{{ route('control-reports.index', ['kind' => $kind] + $filters + ['format' => 'csv']) }}">Descargar CSV completo</a><a class="button" target="_blank" rel="noopener" href="{{ route('control-reports.index', ['kind' => $kind] + $filters + ['format' => 'print']) }}">Preparar impresión / PDF</a>@else<button type="button" class="secondary" onclick="window.print()">Imprimir esta página</button>@endcan
        @endif
    </div></header>
    @if(array_filter($filters, fn ($value) => $value !== null && $value !== ''))
    <dl class="report-applied-filters">
        @foreach($filters as $filter => $value)
            @if($value !== null && $value !== '')
                @php($display = match($filter) {
                    'product_id' => ($productChoices->firstWhere('id', $value)?->internal_code ?? $value),
                    'category_id' => ($categoryChoices->firstWhere('id', $value)?->name ?? $value),
                    'location_id' => ($locationChoices->firstWhere('id', $value)?->code ?? $value),
                    'worker_id' => (($selectedWorker = $workerChoices->firstWhere('id', $value)) ? $selectedWorker->codigo.' · '.$selectedWorker->nombres.' '.$selectedWorker->apellidos : $value),
                    'status' => __('control.states.'.$value, [], 'es'), 'type' => __('control.kinds.'.$value, [], 'es'),
                    'custody' => ['pending' => 'Pendientes', 'overdue' => 'Vencidos', 'returned' => 'Devueltos'][$value],
                    default => $value,
                })
                <div><dt>{{ ['from' => 'Desde', 'to' => 'Hasta', 'q' => 'Búsqueda', 'product_id' => 'Artículo', 'category_id' => 'Categoría', 'location_id' => 'Ubicación', 'worker_id' => 'Trabajador', 'status' => 'Estado', 'type' => 'Tipo', 'custody' => 'Responsabilidad'][$filter] }}</dt><dd>{{ $display }}</dd></div>
            @endif
        @endforeach
    </dl>
    @endif
    <p class="report-scope-note">
        @if($kind === 'stock')Saldo físico reconstruido hasta la fecha de corte inclusive; sin corte se consideran todos los movimientos registrados. El estado del catálogo es actual.
        @elseif($kind === 'movements')Una fila por movimiento físico. El saldo posterior incluye los movimientos anteriores al filtro y se calcula por artículo y ubicación.
        @elseif(in_array($kind, ['responsibilities', 'loans', 'availability']))La custodia y el vencimiento muestran la situación actual, incluidas reaperturas por compensación. Las fechas documentales no reconstruyen una custodia histórica. “En almacén” indica presencia física; la habilitación para prestar se comprueba en Disponibilidad.
        @elseif($kind === 'audit')Eventos de operaciones críticas, con su autoría y referencias. No modifica el historial.
        @else
            Una fila por línea o ítem del documento. Las cantidades de borradores, cancelados y documentos compensados no representan por sí solas un saldo físico vigente.
        @endif
    </p>
    @if($printing && in_array($kind, ['deliveries', 'remittances', 'loans', 'returns', 'corrections']))
        @include('control.report-print-documents')
    @else
        @include('control.report-table')
    @endif
    @if(!$printing)<div class="report-pagination">{{ $rows->links() }}</div>@endif
    <footer class="report-result-footer"><span>{{ $printing ? 'Informe completo de la consulta' : 'Vista paginada · CSV e impresión completa conservan los filtros' }}</span><span>No se suman cantidades de unidades incompatibles.</span></footer>
</section>
<p class="report-catalog-note">Las descripciones y categorías corresponden al catálogo actual. Los trabajadores confirmados se muestran con sus datos documentales y las unidades se conservan desde cada línea.</p>
@endsection
