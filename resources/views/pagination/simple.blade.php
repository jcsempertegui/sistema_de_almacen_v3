@if($paginator->hasPages())
<nav class="pagination" aria-label="Paginación">
    <div>@if($paginator->onFirstPage())<span>Anterior</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>@endif</div>
    <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
    <div>@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>@else<span>Siguiente</span>@endif</div>
</nav>
@endif
