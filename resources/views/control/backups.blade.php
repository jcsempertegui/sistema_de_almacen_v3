@extends('layouts.app')
@section('title','Respaldos')
@section('content')
<h1>Copias de seguridad</h1><p>Los archivos son privados. Verificar SHA-256 no acredita por sí solo una restauración. La recuperación de la principal requiere un procedimiento administrativo separado.</p>
@can('backups.generate')<form method="POST" action="{{ route('backups.request') }}">@csrf<button>Solicitar respaldo completo</button></form>@endcan
<div class="table-wrap"><table><thead><tr><th>Solicitud</th><th>Estado</th><th>Tamaño</th><th>SHA-256</th><th>Acciones</th></tr></thead><tbody>@foreach($backups as $run)<tr><td>#{{ $run->id }}</td><td>{{ __('control.states.'.$run->status, [], 'es') }}</td><td>{{ $run->size_bytes??'Pendiente' }}</td><td><code>{{ $run->sha256??'Pendiente' }}</code></td><td>@if(in_array($run->status,['generated','verified']))
@can('backups.download')<a href="{{ route('backups.download',$run) }}">Descargar SQL</a>@endcan
@can('backups.inspect')<a href="{{ route('backups.manifest',$run) }}">Consultar manifiesto</a>@endcan
@can('backups.verify')<form method="POST" action="{{ route('backups.verify',$run) }}">@csrf<button>Verificar integridad</button></form>@endcan
@can('backup_rehearsals.request')<form method="POST" action="{{ route('backups.rehearsal',$run) }}">@csrf<label>Motivo del ensayo<input required name="reason" maxlength="1000"></label><button>Solicitar restauración aislada</button></form>@endcan
@endif</td></tr>@endforeach</tbody></table></div>{{ $backups->links() }}
<h2>Ensayos de restauración</h2><div class="table-wrap"><table><thead><tr><th>Ensayo / respaldo</th><th>Estado</th><th>Destino aislado</th><th>Responsables</th><th>Acciones</th></tr></thead><tbody>@foreach($rehearsals as $r)<tr><td>#{{ $r->id }} / #{{ $r->backup_run_id }}</td><td>{{ __('control.states.'.$r->status, [], 'es') }}</td><td>{{ $r->target_database }}</td><td>Solicitante #{{ $r->requested_by }} / autorizador #{{ $r->approved_by??'pendiente' }}</td><td>@can('backup_rehearsals.authorize')@if($r->status==='requested')<form method="POST" action="{{ route('rehearsals.approve',$r->id) }}">@csrf<button>Autorizar ensayo</button></form>@endif @if(in_array($r->status,['requested','approved']))<form method="POST" action="{{ route('rehearsals.revoke',$r->id) }}">@csrf<label>Motivo de revocación<input required name="reason" maxlength="1000"></label><button>Revocar</button></form>@endif
@endcan</td></tr>@endforeach</tbody></table></div>{{ $rehearsals->links() }}
@endsection
