@extends('layouts.app')
@section('title', $label)
@section('body-class', 'reports-page')
@section('content')
<div class="page-intro report-page-intro"><div><span class="panel-eyebrow">INFORMES DEL ALMACÉN</span><h1>{{ $group['label'] }}</h1><p>Consulta el detalle, filtra la información y prepara tus informes.</p></div><span class="site-badge"><x-icon name="chart" /> Río Grande</span></div>
@php($consultasVisibles = array_filter($group['kinds'], fn ($consulta) => \Illuminate\Support\Facades\Gate::allows(app(\App\Services\ReportesControl::class)->permiso($consulta))))
@if(count($consultasVisibles) > 1)
<nav class="report-tabs" aria-label="Consultas de este grupo">
    @foreach($consultasVisibles as $tab)
        @can(app(\App\Services\ReportesControl::class)->permiso($tab))
            <a href="{{ route('reportes-control.index', $tab) }}" @if($clase === $tab) aria-current="page" @endif>{{ \App\Services\ReportesControl::LABELS[$tab] }}</a>
        @endcan
    @endforeach
</nav>
@endif
@if(!$printing)
<section class="card report-filter-panel" aria-labelledby="report-filter-title">
    <div class="operational-heading"><h2 id="report-filter-title">Personaliza tu consulta</h2><span>Los filtros se aplican también al CSV y a la impresión.</span></div>
    <form method="GET" action="{{ route('reportes-control.index', $clase) }}">
        <div class="report-filter-grid">
            <div class="field"><label for="report-q">Buscar</label><input id="report-q" type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Artículo, código, documento o persona"></div>
            @if(in_array('from', $availableFilters))<div class="field"><label for="report-from">Desde</label><input id="report-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>@endif
            @if(in_array('to', $availableFilters))<div class="field"><label for="report-to">{{ $clase === 'stock' ? 'Existencias al cierre de' : 'Hasta (inclusive)' }}</label><input id="report-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>@endif
        </div>
        <details class="simple-details" @if(collect($filters)->except(['q', 'from', 'to'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()) open @endif><summary>Más filtros</summary><div class="report-filter-grid">
            @if(in_array('producto_id', $availableFilters))<div class="field"><label for="report-producto">Artículo</label><select id="report-producto" name="producto_id"><option value="">Todos los artículos</option>@foreach($productChoices as $producto)<option value="{{ $producto->id }}" @selected(($filters['producto_id'] ?? '') == $producto->id)>{{ $producto->codigo_interno }} · {{ $producto->nombre }}</option>@endforeach</select></div>@endif
            @if(in_array('categoria_id', $availableFilters))<div class="field"><label for="report-categoria">Categoría</label><select id="report-categoria" name="categoria_id"><option value="">Todas las categorías</option>@foreach($categoryChoices as $categoria)<option value="{{ $categoria->id }}" @selected(($filters['categoria_id'] ?? '') == $categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></div>@endif
            @if(in_array('ubicacion_id', $availableFilters))<div class="field"><label for="report-ubicacion">Ubicación</label><select id="report-ubicacion" name="ubicacion_id"><option value="">Todas las ubicaciones</option>@foreach($locationChoices as $ubicacion)<option value="{{ $ubicacion->id }}" @selected(($filters['ubicacion_id'] ?? '') == $ubicacion->id)>{{ $ubicacion->codigo }} · {{ $ubicacion->nombre }}</option>@endforeach</select></div>@endif
            @if(in_array('trabajador_id', $availableFilters))<div class="field"><label for="report-trabajador">Trabajador responsable</label><select id="report-trabajador" name="trabajador_id"><option value="">Todos los trabajadores</option>@foreach($workerChoices as $trabajador)<option value="{{ $trabajador->id }}" @selected(($filters['trabajador_id'] ?? '') == $trabajador->id)>{{ $trabajador->codigo }} · {{ $trabajador->nombres }} {{ $trabajador->apellidos }}{{ $trabajador->activo ? '' : ' · Inactivo' }}</option>@endforeach</select></div>@endif
            @if(in_array('estado', $availableFilters))<div class="field"><label for="report-estado">Estado documental</label><select id="report-estado" name="estado"><option value="">Todos los estados</option>@foreach($clase === 'corrections' ? ['draft', 'requested', 'authorized', 'rejected', 'cancelled', 'executed'] : ['draft', 'confirmed', 'cancelled'] as $estado)<option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ __('control.states.'.$estado, [], 'es') }}</option>@endforeach</select></div>@endif
            @if(in_array('tipo', $availableFilters))<div class="field"><label for="report-tipo">Tipo de documento</label><select id="report-tipo" name="tipo"><option value="">Todos los tipos</option>@foreach($clase === 'corrections' ? ['adjustment', 'compensation'] : ['opening', 'receipt', 'issue'] as $tipo)<option value="{{ $tipo }}" @selected(($filters['tipo'] ?? '') === $tipo)>{{ __('control.kinds.'.$tipo, [], 'es') }}</option>@endforeach</select></div>@endif
            @if(in_array('custody', $availableFilters))<div class="field"><label for="report-custody">Responsabilidad actual</label><select id="report-custody" name="custody"><option value="">Todas</option><option value="pending" @selected(($filters['custody'] ?? '') === 'pending')>Pendientes, incluidos vencidos</option><option value="overdue" @selected(($filters['custody'] ?? '') === 'overdue')>Solo vencidos</option>@if($clase === 'loans')<option value="returned" @selected(($filters['custody'] ?? '') === 'returned')>Devueltos</option>@endif</select></div>@endif
        </div>
        </details>
        <div class="report-filter-actions"><button type="submit"><x-icon name="chart" /> Consultar informe</button><a class="button secondary" href="{{ route('reportes-control.index', $clase) }}">Limpiar filtros</a>
        @if(in_array('from', $availableFilters))<a href="{{ route('reportes-control.index', ['clase' => $clase, 'from' => now('America/La_Paz')->startOfMonth()->toDateString(), 'to' => now('America/La_Paz')->toDateString()]) }}">Este mes</a>@endif
        @if($clase === 'deliveries')<a href="{{ route('reportes-control.index', ['clase' => $clase, 'q' => 'Dotación semestral']) }}">Buscar dotaciones semestrales</a>@endif
        </div>
    </form>
</section>
@endif
<div class="report-summary" aria-label="Resumen del resultado">
    @foreach($summary as $metric => $value)<div><span>{{ $metric }}</span><strong>{{ number_format($value, 0, ',', '.') }}</strong></div>@endforeach
</div>
<section class="card report-result" aria-labelledby="report-resultado-title">
    <header class="report-result-header"><div><span class="panel-eyebrow">CONFIPETROL · PLANTA RÍO GRANDE</span><h2 id="report-resultado-title">{{ $label }}</h2><p>Generado el {{ $generatedAt }} · Hora de Bolivia · {{ auth()->user()->nombre }}</p></div><div class="report-export-actions">
        @if($printing)<button type="button" onclick="window.print()">Imprimir / Guardar PDF</button><a class="button secondary" href="{{ route('reportes-control.index', ['clase' => $clase] + $filters) }}">Volver al informe</a>
        @else
            @can(app(\App\Services\ReportesControl::class)->permiso($clase, 'export'))<a class="button secondary" href="{{ route('reportes-control.index', ['clase' => $clase] + $filters + ['format' => 'csv']) }}">Descargar CSV completo</a><a class="button" target="_blank" rel="noopener" href="{{ route('reportes-control.index', ['clase' => $clase] + $filters + ['format' => 'print']) }}">Preparar impresión / PDF</a>@else<button type="button" class="secondary" onclick="window.print()">Imprimir esta página</button>@endcan
        @endif
    </div></header>
    @if(array_filter($filters, fn ($value) => $value !== null && $value !== ''))
    <dl class="report-applied-filters">
        @foreach($filters as $filter => $value)
            @if($value !== null && $value !== '')
                @php($display = match($filter) {
                    'producto_id' => ($productChoices->firstWhere('id', $value)?->codigo_interno ?? $value),
                    'categoria_id' => ($categoryChoices->firstWhere('id', $value)?->nombre ?? $value),
                    'ubicacion_id' => ($locationChoices->firstWhere('id', $value)?->codigo ?? $value),
                    'trabajador_id' => (($selectedWorker = $workerChoices->firstWhere('id', $value)) ? $selectedWorker->codigo.' · '.$selectedWorker->nombres.' '.$selectedWorker->apellidos : $value),
                    'estado' => __('control.states.'.$value, [], 'es'), 'tipo' => __('control.kinds.'.$value, [], 'es'),
                    'custody' => ['pending' => 'Pendientes', 'overdue' => 'Vencidos', 'returned' => 'Devueltos'][$value],
                    default => $value,
                })
                <div><dt>{{ ['from' => 'Desde', 'to' => 'Hasta', 'q' => 'Búsqueda', 'producto_id' => 'Artículo', 'categoria_id' => 'Categoría', 'ubicacion_id' => 'Ubicación', 'trabajador_id' => 'Trabajador', 'estado' => 'Estado', 'tipo' => 'Tipo', 'custody' => 'Responsabilidad'][$filter] }}</dt><dd>{{ $display }}</dd></div>
            @endif
        @endforeach
    </dl>
    @endif
    <p class="report-scope-note">
        @if($clase === 'stock')Saldo físico reconstruido hasta la fecha de corte inclusive; sin corte se consideran todos los movimientos registrados. El estado del catálogo es actual.
        @elseif($clase === 'movements')Una fila por movimiento físico. El saldo posterior incluye los movimientos anteriores al filtro y se calcula por artículo y ubicación.
        @elseif(in_array($clase, ['responsibilities', 'loans', 'availability']))La custodia y el vencimiento muestran la situación actual, incluidas reaperturas por compensación. Las fechas documentales no reconstruyen una custodia histórica. “En almacén” indica presencia física; la habilitación para prestar se comprueba en Disponibilidad.
        @elseif($clase === 'audit')Eventos de operaciones críticas, con su autoría y referencias. No modifica el historial.
        @else
            Una fila por línea o ítem del documento. Las cantidades de borradores, cancelados y documentos compensados no representan por sí solas un saldo físico vigente.
        @endif
    </p>
    @if($printing && in_array($clase, ['deliveries', 'remittances', 'loans', 'returns', 'corrections']))
        @include('control.report-print-documents')
    @else
        @include('control.report-table')
    @endif
    @if(!$printing)<div class="report-pagination">{{ $rows->links() }}</div>@endif
    <footer class="report-result-footer"><span>{{ $printing ? 'Informe completo de la consulta' : 'Vista paginada · CSV e impresión completa conservan los filtros' }}</span><span>No se suman cantidades de unidades incompatibles.</span></footer>
</section>
<p class="report-catalog-note">Las descripciones y categorías corresponden al catálogo actual. Los trabajadores confirmados se muestran con sus datos documentales y las unidades se conservan desde cada línea.</p>
@endsection
