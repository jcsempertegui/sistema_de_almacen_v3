@extends('layouts.app')
@section('title', 'Detalle del trabajador')
@section('content')
<h1>{{ $trabajador->nombres }} {{ $trabajador->apellidos }}</h1>
<section class="card">
    <dl class="form-grid">
        <div><dt>Código interno</dt><dd>{{ $trabajador->codigo }}</dd></div>
        <div><dt>Documento de identidad</dt><dd>{{ $trabajador->documento_identidad ?? 'No registrado' }}</dd></div>
        <div><dt>Cargo</dt><dd>{{ $trabajador->cargo ?? 'No registrado' }}</dd></div>
        <div><dt>Área</dt><dd>{{ $trabajador->area ?? 'No registrada' }}</dd></div>
        <div><dt>Estado</dt><dd>{{ $trabajador->activo ? 'Activo' : 'Inactivo' }}</dd></div>
        <div><dt>Registrado por</dt><dd>{{ $trabajador->creador?->nombre ?? 'No registrado' }}</dd></div>
        <div><dt>Última edición por</dt><dd>{{ $trabajador->modificador?->nombre ?? 'No registrada' }}</dd></div>
    </dl>
    <div class="actions">@can('update', $trabajador)<a class="button" href="{{ route('trabajadores.edit', $trabajador) }}">Editar trabajador</a>@endcan<a href="{{ route('trabajadores.index') }}">Volver al listado</a></div>
</section>
@endsection
