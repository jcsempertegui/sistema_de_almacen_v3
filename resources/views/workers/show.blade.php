@extends('layouts.app')
@section('title', 'Detalle del trabajador')
@section('content')
<h1>{{ $worker->nombres }} {{ $worker->apellidos }}</h1>
<section class="card">
    <dl class="form-grid">
        <div><dt>Código interno</dt><dd>{{ $worker->codigo }}</dd></div>
        <div><dt>Documento de identidad</dt><dd>{{ $worker->documento_identidad ?? 'No registrado' }}</dd></div>
        <div><dt>Cargo</dt><dd>{{ $worker->cargo ?? 'No registrado' }}</dd></div>
        <div><dt>Área</dt><dd>{{ $worker->area ?? 'No registrada' }}</dd></div>
        <div><dt>Estado</dt><dd>{{ $worker->is_active ? 'Activo' : 'Inactivo' }}</dd></div>
        <div><dt>Registrado por</dt><dd>{{ $worker->creator?->name ?? 'No registrado' }}</dd></div>
        <div><dt>Última edición por</dt><dd>{{ $worker->updater?->name ?? 'No registrada' }}</dd></div>
    </dl>
    <div class="actions">@can('update', $worker)<a class="button" href="{{ route('workers.edit', $worker) }}">Editar trabajador</a>@endcan<a href="{{ route('workers.index') }}">Volver al listado</a></div>
</section>
@endsection
