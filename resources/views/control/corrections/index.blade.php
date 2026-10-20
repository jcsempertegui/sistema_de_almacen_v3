@extends('layouts.app')
@section('title', $kind==='adjustment'?'Ajustes':'Compensaciones')
@section('content')
<h1>{{ $kind==='adjustment'?'Ajustes de inventario':'Compensaciones de documentos' }}</h1>
<p>Los borradores no modifican existencias. La ejecución requiere autorización de otra persona y representa una corrección física efectiva.</p>
@can(($kind==='adjustment'?'adjustments':'compensations').'.create')<a class="button" href="{{ route('corrections.create',$kind) }}">Nueva corrección</a>@endcan
<form method="GET"><label>Buscar por motivo<input name="q" value="{{ request('q') }}" maxlength="100"></label><button>Buscar</button></form>
<div class="table-wrap"><table><thead><tr><th>Documento</th><th>Fecha</th><th>Estado</th><th>Motivo</th></tr></thead><tbody>@forelse($documents as $doc)<tr><td><a href="{{ route('corrections.show',[$kind,$doc->id]) }}">{{ $doc->code }}</a></td><td>{{ $doc->document_date->format('Y-m-d') }}</td><td>{{ __('control.states.'.$doc->status, [], 'es') }}</td><td>{{ $doc->reason }}</td></tr>@empty<tr><td colspan="4">Sin correcciones.</td></tr>@endforelse</tbody></table></div>{{ $documents->links() }}
@endsection
