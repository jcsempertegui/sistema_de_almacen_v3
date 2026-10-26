@if($charts['movements'] !== null || $charts['loans'] !== null)
<section class="dashboard-charts" aria-labelledby="charts-heading">
    <div class="charts-heading"><div><h2 id="charts-heading">El almacén en cifras</h2><p>Actividad registrada y herramientas que siguen en custodia.</p></div><span>Actualizado {{ $charts['as_of'] }} · Bolivia</span></div>
    <div class="chart-grid">
        @if($charts['movements'] !== null)
            @php
                $movement = $charts['movements'];
                $peak = max(array_merge(array_column($movement['days'], 'in'), array_column($movement['days'], 'out')));
                $scale = max(4, (int) ceil($peak / 4) * 4);
            @endphp
            <article class="card chart-card chart-activity">
                <header class="chart-card-header"><div><h3>Actividad de los últimos 7 días</h3><p>Movimientos por línea confirmada</p></div><span class="chart-total">{{ $movement['weekly_total'] }}<small>movimientos</small></span></header>
                <div class="chart-legend"><span><i class="chart-dot incoming"></i>Entradas</span><span><i class="chart-dot outgoing"></i>Salidas</span></div>
                @if($movement['weekly_total'] > 0)
                    <svg class="activity-chart" viewBox="0 0 560 210" role="img" aria-labelledby="activity-title activity-description">
                        <title id="activity-title">Entradas y salidas por día</title><desc id="activity-description">{{ $movement['weekly_total'] }} movimientos en siete días. La tabla Ver datos diarios contiene los valores exactos.</desc>
                        @foreach([0, 1, 2] as $tick)
                            <line x1="38" x2="550" y1="{{ 168 - $tick * 64 }}" y2="{{ 168 - $tick * 64 }}" class="chart-gridline" />
                            <text x="27" y="{{ 172 - $tick * 64 }}" text-anchor="end" class="chart-axis">{{ (int) ($scale * $tick / 2) }}</text>
                        @endforeach
                        @foreach($movement['days'] as $index => $day)
                            @php($x = 52 + $index * 71)
                            <rect x="{{ $x }}" y="{{ 168 - $day['in'] / $scale * 128 }}" width="18" height="{{ $day['in'] / $scale * 128 }}" rx="3" class="chart-bar-in"><title>{{ $day['date'] }}: {{ $day['in'] }} entradas</title></rect>
                            <rect x="{{ $x + 22 }}" y="{{ 168 - $day['out'] / $scale * 128 }}" width="18" height="{{ $day['out'] / $scale * 128 }}" rx="3" class="chart-bar-out"><title>{{ $day['date'] }}: {{ $day['out'] }} salidas</title></rect>
                            <text x="{{ $x + 20 }}" y="194" text-anchor="middle" class="chart-axis">{{ $day['label'] }}</text>
                        @endforeach
                    </svg>
                @else
                    <div class="chart-empty"><x-icon name="chart" /><strong>Sin movimientos en los últimos 7 días</strong><p>La gráfica aparecerá al confirmar operaciones.</p></div>
                @endif
                <details class="chart-data"><summary>Ver datos diarios</summary><div class="table-wrap"><table><caption class="sr-only">Movimientos por día, zona horaria de Bolivia</caption><thead><tr><th>Fecha</th><th>Entradas</th><th>Salidas</th></tr></thead><tbody>@foreach($movement['days'] as $day)<tr><td>{{ $day['date'] }}</td><td>{{ $day['in'] }}</td><td>{{ $day['out'] }}</td></tr>@endforeach</tbody></table></div></details>
            </article>
            <article class="card chart-card">
                <header class="chart-card-header"><div><h3>Entradas frente a salidas</h3><p>Últimos 30 días, incluido hoy</p></div></header>
                <div class="donut-content">
                    <svg class="donut-chart" viewBox="0 0 160 160" role="img" aria-labelledby="balance-title balance-description">
                        <title id="balance-title">Proporción de movimientos de entrada y salida</title><desc id="balance-description">{{ $movement['incoming'] }} entradas y {{ $movement['outgoing'] }} salidas. Se cuentan movimientos, no cantidades de materiales.</desc>
                        <circle cx="80" cy="80" r="60" fill="none" stroke="#edf1f6" stroke-width="15" />
                        @if($movement['total'] > 0)
                            <circle cx="80" cy="80" r="60" fill="none" stroke="#1769e0" stroke-width="15" />
                            <circle cx="80" cy="80" r="60" fill="none" stroke="#159570" stroke-width="15" pathLength="100" stroke-dasharray="{{ $movement['incoming'] / $movement['total'] * 100 }} 100" transform="rotate(-90 80 80)" />
                        @endif
                        <text x="80" y="80" text-anchor="middle" class="donut-value">{{ $movement['total'] }}</text><text x="80" y="100" text-anchor="middle" class="donut-label">movimientos</text>
                    </svg>
                    <dl class="donut-legend"><div><dt><i class="chart-dot incoming"></i>Entradas</dt><dd>{{ $movement['incoming'] }}</dd></div><div><dt><i class="chart-dot outgoing"></i>Salidas</dt><dd>{{ $movement['outgoing'] }}</dd></div></dl>
                </div>
                <p class="chart-footnote">{{ $movement['total'] === 0 ? 'Todavía no hay movimientos en este período.' : 'Incluye los movimientos de ajustes y compensaciones ejecutados.' }} No suma unidades de productos distintos.</p>
                <details class="chart-data"><summary>Ver datos del período</summary><div class="table-wrap"><table><caption class="sr-only">Entradas y salidas de los últimos 30 días</caption><thead><tr><th>Dirección</th><th>Movimientos</th></tr></thead><tbody><tr><td>Entradas</td><td>{{ $movement['incoming'] }}</td></tr><tr><td>Salidas</td><td>{{ $movement['outgoing'] }}</td></tr><tr><td>Total</td><td>{{ $movement['total'] }}</td></tr></tbody></table></div></details>
            </article>
        @endif
        @if($charts['loans'] !== null)
            @php($loans = $charts['loans'])
            @php($maximum = max(1, $loans['other'], ...array_column($loans['workers'], 'total')))
            <article class="card chart-card">
                <header class="chart-card-header"><div><h3>Herramientas pendientes</h3><p>Custodia actual por trabajador</p></div><span class="chart-total">{{ $loans['total'] }}<small>herramientas</small></span></header>
                @if($loans['total'] > 0)
                    <div class="custody-bars" role="list" aria-label="Herramientas pendientes por trabajador">
                        @foreach($loans['workers'] as $worker)
                            <div class="custody-row" role="listitem"><div class="custody-label"><span>{{ $worker['label'] }}<small>{{ $worker['code'] }}</small></span><strong>{{ $worker['total'] }}</strong></div><div class="custody-track" aria-hidden="true"><span style="width:{{ $worker['total'] / $maximum * 100 }}%"></span></div></div>
                        @endforeach
                        @if($loans['other'] > 0)<div class="custody-row" role="listitem"><div class="custody-label"><span>Otros trabajadores</span><strong>{{ $loans['other'] }}</strong></div><div class="custody-track" aria-hidden="true"><span style="width:{{ $loans['other'] / $maximum * 100 }}%"></span></div></div>@endif
                    </div>
                @else
                    <div class="chart-empty"><x-icon name="check" /><strong>Sin herramientas pendientes</strong><p>No hay herramientas en custodia de trabajadores.</p></div>
                @endif
                <p class="chart-footnote">Cinco trabajadores con más herramientas y el resto agrupado. Considera devoluciones y reaperturas, sin sumar préstamos históricos cerrados.</p>
                <details class="chart-data"><summary>Ver datos de custodia</summary><div class="table-wrap"><table><caption class="sr-only">Herramientas pendientes por trabajador, con total y otros trabajadores agrupados</caption><thead><tr><th>Trabajador</th><th>Código</th><th>Herramientas</th></tr></thead><tbody>@forelse($loans['workers'] as $worker)<tr><td>{{ $worker['label'] }}</td><td>{{ $worker['code'] }}</td><td>{{ $worker['total'] }}</td></tr>@empty<tr><td colspan="3">Sin custodia pendiente.</td></tr>@endforelse @if($loans['other'] > 0)<tr><td>Otros trabajadores</td><td>—</td><td>{{ $loans['other'] }}</td></tr>@endif<tr><td>Total</td><td>—</td><td>{{ $loans['total'] }}</td></tr></tbody></table></div></details>
            </article>
        @endif
    </div>
    <p class="chart-explanation">Las entradas y salidas cuentan líneas del registro de movimientos, no documentos ni cantidades físicas. Los borradores no se incluyen.</p>
</section>
@endif
