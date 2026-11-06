@extends('layouts.app')
@section('title', $clase==='adjustment'?'Ajustes':'Compensaciones')
@section('content')
<h1>{{ $clase === 'adjustment' ? 'Corregir diferencia de inventario' : 'Compensar operación confirmada' }}</h1>
<p>{{ $clase === 'adjustment' ? 'Corregir una diferencia de inventario: registra el conteo físico cuando no coincide con el sistema.' : 'Compensar una operación confirmada: contrarresta sus efectos conservando el documento y sus movimientos originales.' }}</p>
<p>Los borradores no modifican existencias. La ejecución requiere autorización de otra persona y representa una corrección física efectiva.</p>
@can(($clase==='adjustment'?'adjustments':'compensations').'.create')<a class="button" href="{{ route('corrections.create',$clase) }}">Nueva corrección</a>@endcan
<form method="GET"><label>Buscar por motivo<input name="q" value="{{ request('q') }}" maxlength="100"></label><button>Buscar</button></form>
<div class="table-wrap"><table><thead><tr><th>Documento</th><th>Fecha</th><th>Estado</th><th>Motivo</th></tr></thead><tbody>@forelse($documents as $doc)<tr><td><a href="{{ route('corrections.show',[$clase,$doc->id]) }}">{{ $doc->codigo }}</a></td><td>{{ $doc->fecha_documento->format('Y-m-d') }}</td><td>{{ __('control.states.'.$doc->estado, [], 'es') }}</td><td>{{ $doc->motivo }}</td></tr>@empty<tr><td colspan="4">Sin correcciones.</td></tr>@endforelse</tbody></table></div>{{ $documents->links() }}
@endsection
