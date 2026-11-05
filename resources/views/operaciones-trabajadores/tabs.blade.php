<nav class="report-tabs" aria-label="Herramientas y devoluciones">
@can('loans.view')<a href="{{ route('operaciones-trabajadores.index', 'loans') }}" @if($clase === 'loans') aria-current="page" @endif>Herramientas prestadas</a>@endcan
@can('returns.view')<a href="{{ route('operaciones-trabajadores.index', 'returns') }}" @if($clase === 'returns') aria-current="page" @endif>Devoluciones</a>@endcan
@can('availability.view')<a href="{{ route('availability.index') }}">Disponibilidad</a>@endcan
</nav>
