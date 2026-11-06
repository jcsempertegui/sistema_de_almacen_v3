@if($paginator->hasPages())
<nav class="pagination" aria-label="Paginación">
    @if($paginator->onFirstPage())<span class="pagination-control" aria-disabled="true">@else<a class="pagination-control" href="{{ \App\Support\RutasEspanol::urlPublica($paginator->previousPageUrl()) }}" rel="prev">@endif
        <svg class="pagination-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>Anterior
    @if($paginator->onFirstPage())</span>@else</a>@endif
    <span class="pagination-status">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a class="pagination-control" href="{{ \App\Support\RutasEspanol::urlPublica($paginator->nextPageUrl()) }}" rel="next">@else<span class="pagination-control" aria-disabled="true">@endif
        Siguiente<svg class="pagination-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
    @if($paginator->hasMorePages())</a>@else</span>@endif
</nav>
@endif
