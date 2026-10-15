@extends('layouts.app')
@section('title', $location->exists ? 'Editar ubicación' : 'Nueva ubicación')
@section('content')
<h1>{{ $location->exists ? 'Editar ubicación' : 'Nueva ubicación' }}</h1>
<section class="card"><form method="POST" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}">
    @csrf @if($location->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="code">Código</label><input id="code" name="code" value="{{ old('code', $location->code) }}" maxlength="60" required><span class="help">El código se guarda en mayúsculas y no se reutiliza al desactivar.</span></div>
        <div class="field"><label for="name">Nombre</label><input id="name" name="name" value="{{ old('name', $location->name) }}" maxlength="150" required></div>
        <div class="field"><label for="type">Tipo</label><select id="type" name="type">@foreach(\App\Models\Location::TYPES as $code => $label)<option value="{{ $code }}" @selected(old('type', $location->type ?? 'sector') === $code)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="parent_id">Ubicación padre (opcional)</label><select id="parent_id" name="parent_id"><option value="">Sin padre — raíz</option>
            @if($location->parent_id && ! $parents->contains('id', $location->parent_id))
                <option value="{{ $location->parent_id }}" @selected((string) old('parent_id', $location->parent_id) === (string) $location->parent_id)>{{ $paths[$location->parent_id] }} — Padre histórico inactivo</option>
            @endif
            @foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string) old('parent_id', $location->parent_id) === (string) $parent->id)>{{ $paths[$parent->id] }}</option>@endforeach
        </select><span class="help">El padre y sus antecesores deben estar activos al crear, cambiar de padre o reactivar. No se permite elegir la propia ubicación ni sus descendientes.</span></div>
        <div class="field"><label for="is_storable">Permite almacenar</label><select id="is_storable" name="is_storable"><option value="1" @selected((string) old('is_storable', (int) $location->is_storable) === '1')>Sí</option><option value="0" @selected((string) old('is_storable', (int) $location->is_storable) === '0')>No</option></select><span class="help">Una ubicación no almacenable puede organizar otras ubicaciones.</span></div>
        <div class="field"><label for="description">Descripción (opcional)</label><textarea id="description" name="description" maxlength="10000">{{ old('description', $location->description) }}</textarea></div>
        <div class="field"><label for="is_active">Estado</label>@if($location->exists && ! auth()->user()->can('changeStatus', $location))<input id="is_active" value="{{ $location->is_active ? 'Activa' : 'Inactiva' }}" disabled><input type="hidden" name="is_active" value="{{ (int) $location->is_active }}">@else<select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $location->is_active) === '1')>Activa</option><option value="0" @selected((string) old('is_active', (int) $location->is_active) === '0')>Inactiva</option></select>@endif<span class="help">Desactivar conserva el registro y exige que no tenga hijos activos ni unidades asignadas.</span></div>
    </div>
    <div class="actions"><button type="submit">Guardar ubicación</button><a href="{{ route('locations.index') }}">Cancelar</a></div>
</form></section>
@endsection
