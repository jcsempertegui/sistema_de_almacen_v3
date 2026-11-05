@extends('layouts.app')
@section('title', $trabajador->exists ? 'Editar trabajador' : 'Nuevo trabajador')
@section('content')
<h1>{{ $trabajador->exists ? 'Editar trabajador' : 'Nuevo trabajador' }}</h1>
<section class="card">
    <form method="POST" action="{{ $trabajador->exists ? route('trabajadores.update', $trabajador) : route('trabajadores.store') }}">
        @csrf @if($trabajador->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="codigo">Código interno</label><input id="codigo" name="codigo" value="{{ old('codigo', $trabajador->codigo) }}" maxlength="40" required></div>
            <div class="field"><label for="documento_identidad">Documento de identidad (opcional)</label><input id="documento_identidad" name="documento_identidad" value="{{ old('documento_identidad', $trabajador->documento_identidad) }}" maxlength="40" aria-describedby="documento-help"><span id="documento-help" class="help">Registra el documento completo, incluido su complemento cuando corresponda.</span></div>
            <div class="field"><label for="nombres">Nombres</label><input id="nombres" name="nombres" value="{{ old('nombres', $trabajador->nombres) }}" maxlength="100" required></div>
            <div class="field"><label for="apellidos">Apellidos</label><input id="apellidos" name="apellidos" value="{{ old('apellidos', $trabajador->apellidos) }}" maxlength="100" required></div>
            <div class="field"><label for="cargo">Cargo (opcional)</label><input id="cargo" name="cargo" value="{{ old('cargo', $trabajador->cargo) }}" maxlength="100"></div>
            <div class="field"><label for="area">Área (opcional)</label><input id="area" name="area" value="{{ old('area', $trabajador->area) }}" maxlength="100"></div>
            <div class="field"><label for="activo">Estado</label>@if($trabajador->exists && ! auth()->user()->can('changeStatus', $trabajador))<input value="{{ $trabajador->activo ? 'Activo' : 'Inactivo' }}" disabled><input type="hidden" name="activo" value="{{ (int) $trabajador->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $trabajador->activo) === '1')>Activo</option><option value="0" @selected((string) old('activo', (int) $trabajador->activo) === '0')>Inactivo</option></select>@endif</div>
        </div>
        <div class="actions"><button type="submit">Guardar trabajador</button><a href="{{ route('trabajadores.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
