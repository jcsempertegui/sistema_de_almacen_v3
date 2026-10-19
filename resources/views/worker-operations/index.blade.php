@extends('layouts.app')
@section('title', $label)
@section('content')
<h1>{{ $label }}</h1>
@can($kind.'.create')<a class="button" href="{{ route('worker-operations.create',$kind) }}">Registrar borrador</a>@endcan
<form method="GET" class="card"><label>Trabajador (código o nombre)<input name="q" value="{{ request('q') }}" maxlength="100"></label><label>Estado<select name="status"><option value="">Todos</option>@foreach(['draft'=>'Borrador','confirmed'=>'Confirmado','cancelled'=>'Cancelado'] as $key=>$text)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $text }}</option>@endforeach</select></label>@if($kind==='loans')<label><input type="checkbox" name="pending" value="1" @checked(request('pending'))> Solo obligaciones pendientes</label>@endif<button>Buscar</button></form>
<div class="card table-wrap"><table><thead><tr><th>Documento</th><th>Fecha</th><th>Trabajador</th><th>Estado</th>@if($kind==='loans')<th>Responsabilidad</th>@endif</tr></thead><tbody>
@forelse($documents as $doc)<tr><td><a href="{{ route('worker-operations.show',[$kind,$doc->id]) }}">{{ $doc->code }}</a></td><td>{{ $doc->document_date->format('Y-m-d') }}</td><td>{{ $doc->worker_snapshot['codigo'] ?? $doc->worker->codigo }} · {{ $doc->worker_snapshot['nombres'] ?? $doc->worker->nombres }} {{ $doc->worker_snapshot['apellidos'] ?? $doc->worker->apellidos }}</td><td>{{ ['draft'=>'Borrador','confirmed'=>'Confirmado','cancelled'=>'Cancelado'][$doc->status] }}</td>@if($kind==='loans')<td>{{ ['pending'=>'Pendiente','partial'=>'Parcialmente devuelto','returned'=>'Devuelto','draft'=>'Borrador','cancelled'=>'Cancelado'][$doc->responsibility_status] }}</td>@endif</tr>@empty<tr><td colspan="5">Sin documentos para estos filtros.</td></tr>@endforelse
</tbody></table>{{ $documents->links() }}</div>
@endsection
