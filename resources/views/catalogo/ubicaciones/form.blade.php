@extends('layouts.app')
@section('title', $ubicacion->exists ? 'Editar ubicación' : 'Nueva ubicación')
@section('content')
<h1>{{ $ubicacion->exists ? 'Editar ubicación' : 'Nueva ubicación' }}</h1>
<section class="card"><form method="POST" action="{{ $ubicacion->exists ? route('ubicaciones.update', $ubicacion) : route('ubicaciones.store') }}">
    @csrf @if($ubicacion->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="codigo">Código</label><input id="codigo" name="codigo" value="{{ old('codigo', $ubicacion->codigo) }}" maxlength="60" required><span class="help">El código se guarda en mayúsculas y no se reutiliza al desactivar.</span></div>
        <div class="field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre', $ubicacion->nombre) }}" maxlength="150" required></div>
        <div class="field"><label for="tipo">Tipo</label><select id="tipo" name="tipo">@foreach(\App\Models\Ubicacion::TYPES as $codigo => $label)<option value="{{ $codigo }}" @selected(old('tipo', $ubicacion->tipo ?? 'sector') === $codigo)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="padre_id">Ubicación padre (opcional)</label><select id="padre_id" name="padre_id"><option value="">Sin padre — raíz</option>
            @if($ubicacion->padre_id && ! $parents->contains('id', $ubicacion->padre_id))
                <option value="{{ $ubicacion->padre_id }}" @selected((string) old('padre_id', $ubicacion->padre_id) === (string) $ubicacion->padre_id)>{{ $paths[$ubicacion->padre_id] }} — Padre histórico inactivo</option>
            @endif
            @foreach($parents as $padre)<option value="{{ $padre->id }}" @selected((string) old('padre_id', $ubicacion->padre_id) === (string) $padre->id)>{{ $paths[$padre->id] }}</option>@endforeach
        </select><span class="help">El padre y sus antecesores deben estar activos al crear, cambiar de padre o reactivar. No se permite elegir la propia ubicación ni sus descendientes.</span></div>
        <div class="field"><label for="permite_almacenar">Permite almacenar</label><select id="permite_almacenar" name="permite_almacenar"><option value="1" @selected((string) old('permite_almacenar', (int) $ubicacion->permite_almacenar) === '1')>Sí</option><option value="0" @selected((string) old('permite_almacenar', (int) $ubicacion->permite_almacenar) === '0')>No</option></select><span class="help">Una ubicación no almacenable puede organizar otras ubicaciones.</span></div>
        <div class="field"><label for="descripcion">Descripción (opcional)</label><textarea id="descripcion" name="descripcion" maxlength="10000">{{ old('descripcion', $ubicacion->descripcion) }}</textarea></div>
        <div class="field"><label for="activo">Estado</label>@if($ubicacion->exists && ! auth()->user()->can('changeStatus', $ubicacion))<input id="activo" value="{{ $ubicacion->activo ? 'Activa' : 'Inactiva' }}" disabled><input type="hidden" name="activo" value="{{ (int) $ubicacion->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $ubicacion->activo) === '1')>Activa</option><option value="0" @selected((string) old('activo', (int) $ubicacion->activo) === '0')>Inactiva</option></select>@endif<span class="help">Desactivar conserva el registro y exige que no tenga hijos activos ni unidades asignadas.</span></div>
    </div>
    <div class="actions"><button type="submit">Guardar ubicación</button><a href="{{ route('ubicaciones.index') }}">Cancelar</a></div>
</form></section>
@endsection
