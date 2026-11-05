@extends('layouts.app')
@section('title', $categoria->exists ? 'Editar categoría' : 'Nueva categoría')
@section('content')
<h1>{{ $categoria->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1>
<section class="card"><form method="POST" action="{{ $categoria->exists ? route('categorias.update', $categoria) : route('categorias.store') }}">
    @csrf @if($categoria->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="codigo">Código</label><input id="codigo" name="codigo" value="{{ old('codigo', $categoria->codigo) }}" maxlength="50" required><span class="help">El código se guarda en mayúsculas y no se reutiliza al desactivar.</span></div>
        <div class="field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre', $categoria->nombre) }}" maxlength="150" required></div>
        <div class="field"><label for="descripcion">Descripción (opcional)</label><textarea id="descripcion" name="descripcion" maxlength="10000">{{ old('descripcion', $categoria->descripcion) }}</textarea></div>
        <div class="field"><label for="activo">Estado</label>@if($categoria->exists && ! auth()->user()->can('changeStatus', $categoria))<input id="activo" value="{{ $categoria->activo ? 'Activa' : 'Inactiva' }}" disabled><input type="hidden" name="activo" value="{{ (int) $categoria->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $categoria->activo) === '1')>Activa</option><option value="0" @selected((string) old('activo', (int) $categoria->activo) === '0')>Inactiva</option></select>@endif</div>
    </div>
    <div class="actions"><button type="submit">Guardar categoría</button><a href="{{ route('categorias.index') }}">Cancelar</a></div>
</form></section>
@endsection
