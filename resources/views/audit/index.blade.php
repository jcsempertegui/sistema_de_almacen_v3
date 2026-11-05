@extends('layouts.app')
@section('title', 'Auditoría administrativa')
@section('content')
<h1>Auditoría administrativa</h1>
<form class="search" method="GET" action="{{ route('audit.index') }}"><input name="q" aria-label="Buscar auditoría" placeholder="Operador, cuenta, rol o acción" value="{{ request('q') }}" maxlength="100"><button type="submit">Buscar</button></form>
<div class="card table-wrap">
    <table><thead><tr><th>Fecha</th><th>Operador / origen</th><th>Acción</th><th>Cuenta o rol</th><th>Cambios</th></tr></thead><tbody>
    @forelse($logs as $log)
        <tr><td>{{ $log->fecha_creacion->format('d/m/Y H:i:s') }}</td><td>{{ $log->nombre_actor ?? 'Sistema' }}<br>{{ ['web' => 'Sistema web', 'console' => 'Consola', 'migration' => 'Migración inicial'][$log->origen] ?? $log->origen }}<br>{{ $log->direccion_ip }}</td><td>{{ ['rbac.initialized' => 'Permisos iniciales', 'usuarios.bootstrap_created' => 'Administrador inicial creado', 'usuarios.created' => 'Usuario creado', 'usuarios.updated' => 'Usuario actualizado', 'usuarios.status_changed' => 'Estado de usuario cambiado', 'roles.created' => 'Rol creado', 'roles.updated' => 'Rol actualizado', 'roles.permissions_changed' => 'Permisos de rol cambiados', 'roles.status_changed' => 'Estado de rol cambiado'][$log->accion] ?? $log->accion }}</td><td>{{ $log->rol?->nombre ?? $log->usuario?->nombre }}</td><td><details><summary>Ver antes y después</summary><strong>Antes</strong><pre>{{ json_encode($log->datos_anteriores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre><strong>Después</strong><pre>{{ json_encode($log->datos_posteriores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></td></tr>
    @empty<tr><td colspan="5" class="empty">No se encontraron cambios administrativos.</td></tr>@endforelse
    </tbody></table>
    {{ $logs->links('pagination.simple') }}
</div>
@endsection
