@if($document->compensation_id)
<p class="notice">Documento compensado mediante corrección #{{ $document->compensation_id }}. El original y sus movimientos se conservan.
@can('compensations.view')<a href="{{ route('corrections.show',['compensation',$document->compensation_id]) }}">Consultar compensación</a>@endcan
</p>
@endif
