@extends('layouts.app')
@section('title', $worker->exists ? 'Editar trabajador' : 'Nuevo trabajador')
@section('content')
<h1>{{ $worker->exists ? 'Editar trabajador' : 'Nuevo trabajador' }}</h1>
<section class="card">
    <form method="POST" action="{{ $worker->exists ? route('workers.update', $worker) : route('workers.store') }}">
        @csrf @if($worker->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="codigo">Código interno</label><input id="codigo" name="codigo" value="{{ old('codigo', $worker->codigo) }}" maxlength="40" required></div>
            <div class="field"><label for="documento_identidad">Documento de identidad (opcional)</label><input id="documento_identidad" name="documento_identidad" value="{{ old('documento_identidad', $worker->documento_identidad) }}" maxlength="40" aria-describedby="documento-help"><span id="documento-help" class="help">Registra el documento completo, incluido su complemento cuando corresponda.</span></div>
            <div class="field"><label for="nombres">Nombres</label><input id="nombres" name="nombres" value="{{ old('nombres', $worker->nombres) }}" maxlength="100" required></div>
            <div class="field"><label for="apellidos">Apellidos</label><input id="apellidos" name="apellidos" value="{{ old('apellidos', $worker->apellidos) }}" maxlength="100" required></div>
            <div class="field"><label for="cargo">Cargo (opcional)</label><input id="cargo" name="cargo" value="{{ old('cargo', $worker->cargo) }}" maxlength="100"></div>
            <div class="field"><label for="area">Área (opcional)</label><input id="area" name="area" value="{{ old('area', $worker->area) }}" maxlength="100"></div>
            <div class="field"><label for="is_active">Estado</label>@if($worker->exists && ! auth()->user()->can('changeStatus', $worker))<input value="{{ $worker->is_active ? 'Activo' : 'Inactivo' }}" disabled><input type="hidden" name="is_active" value="{{ (int) $worker->is_active }}">@else<select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $worker->is_active) === '1')>Activo</option><option value="0" @selected((string) old('is_active', (int) $worker->is_active) === '0')>Inactivo</option></select>@endif</div>
        </div>
        <div class="actions"><button type="submit">Guardar trabajador</button><a href="{{ route('workers.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
