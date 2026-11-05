@if($documento->compensation_id)
<p class="notice">Documento compensado mediante corrección #{{ $documento->compensation_id }}. El original y sus movimientos se conservan.
@can('compensations.view')<a href="{{ route('corrections.show',['compensation',$documento->compensation_id]) }}">Consultar compensación</a>@endcan
</p>
@endif
