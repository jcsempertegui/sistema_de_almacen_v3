@if($operations['stock'] !== null || $operations['loans'] !== null)
<section class="operation-metrics" aria-label="Resumen operativo actual">
    @if($operations['stock'] !== null)
        <a class="metric-tile" href="{{ route('stock.index') }}"><span class="metric-icon"><x-icon name="box" /></span><span class="metric-label">Productos con existencias</span><strong>{{ number_format($operations['stock']['with_stock'], 0, ',', '.') }}</strong><small>Productos activos con saldo físico positivo</small><span class="metric-link">Consultar existencias <x-icon name="arrow" /></span></a>
        <a class="metric-tile metric-neutral" href="{{ route('stock.index') }}"><span class="metric-icon"><x-icon name="chart" /></span><span class="metric-label">Productos sin existencias</span><strong>{{ number_format($operations['stock']['without_stock'], 0, ',', '.') }}</strong><small>Activos sin saldo positivo, incluidos los aún no ingresados</small><span class="metric-link">Revisar existencias <x-icon name="arrow" /></span></a>
    @endif
    @if($operations['loans'] !== null)
        <a class="metric-tile metric-teal" href="{{ route('operaciones-trabajadores.index', ['tipo' => 'loans', 'pending' => 1]) }}"><span class="metric-icon"><x-icon name="tool" /></span><span class="metric-label">Herramientas prestadas</span><strong>{{ number_format($operations['loans']['pending'], 0, ',', '.') }}</strong><small>Identificadores en custodia actual de trabajadores</small><span class="metric-link">Revisar préstamos <x-icon name="arrow" /></span></a>
        <a class="metric-tile {{ $operations['loans']['overdue'] > 0 ? 'metric-amber' : 'metric-neutral' }}" href="{{ route('operaciones-trabajadores.index', ['tipo' => 'loans', 'pending' => 1]) }}"><span class="metric-icon"><x-icon name="history" /></span><span class="metric-label">Herramientas vencidas</span><strong>{{ number_format($operations['loans']['overdue'], 0, ',', '.') }}</strong><small>{{ $operations['loans']['due_today'] }} con vencimiento hoy · Sin fecha no se consideran vencidas</small><span class="metric-link">Consultar pendientes <x-icon name="arrow" /></span></a>
    @endif
</section>
@endif

@if(count($operations['drafts']) > 0 || $operations['recent'] !== null)
<div class="operation-workspace">
    @if(count($operations['drafts']) > 0)
    <section class="card pending-panel" aria-labelledby="pending-heading">
        <header class="operational-heading"><div><span class="panel-eyebrow">POR REVISAR</span><h2 id="pending-heading">Documentos en borrador</h2></div><span class="pending-total">{{ array_sum(array_column($operations['drafts'], 'count')) }}</span></header>
        <p>Revisa cada documento antes de confirmar la operación física.</p>
        <div class="pending-list">
            @foreach($operations['drafts'] as $draft)
                <a href="{{ $draft['url'] }}"><span>{{ $draft['label'] }}</span><strong class="{{ $draft['count'] > 0 ? 'has-pending' : '' }}">{{ $draft['count'] }}</strong><x-icon name="arrow" /></a>
            @endforeach
        </div>
        <p class="operational-note"><x-icon name="shield" /> Los borradores no modifican las existencias.</p>
    </section>
    @endif
    @if($operations['recent'] !== null)
    <section class="card recent-panel" aria-labelledby="recent-heading">
        <header class="operational-heading"><div><span class="panel-eyebrow">TRAZABILIDAD</span><h2 id="recent-heading">Últimos movimientos</h2></div><a href="{{ route('movements.index') }}">Ver historial <x-icon name="arrow" /></a></header>
        <p>Últimas cinco líneas registradas, incluidos ajustes y compensaciones.</p>
        @if($operations['recent']->isEmpty())
            <div class="operational-empty"><span class="metric-icon"><x-icon name="transfer" /></span><h3>Aún no hay operaciones confirmadas</h3><p>Al confirmar un documento, sus movimientos aparecerán aquí.</p></div>
        @else
            <div class="table-wrap"><table class="recent-movements"><caption class="sr-only">Últimos movimientos registrados, hora de Bolivia</caption><thead><tr><th>Producto</th><th>Operación</th><th>Ubicación</th><th>Registro</th></tr></thead><tbody>
                @foreach($operations['recent'] as $movement)
                    <tr><td><strong>{{ $movement->producto->codigo_interno }}</strong><small>{{ $movement->producto->nombre }}</small></td><td><span class="movement-direccion {{ $movement->direccion === 'in' ? 'is-in' : 'is-out' }}">{{ $movement->direccion === 'in' ? 'Ingreso' : 'Egreso' }}</span></td><td>{{ $movement->ubicacion->codigo }}</td><td><time datetime="{{ $movement->posted_at->toIso8601String() }}">{{ $movement->posted_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</time></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
    @endif
</div>
@endif
