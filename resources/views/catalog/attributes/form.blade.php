@extends('layouts.app')
@section('title', $attribute->exists ? 'Editar atributo técnico' : 'Nuevo atributo técnico')
@section('content')
<h1>{{ $attribute->exists ? 'Editar atributo técnico' : 'Nuevo atributo técnico' }}</h1>
@php($hasAssociations = $attribute->exists && $attribute->categoryAttributes()->exists())
<section class="card"><form method="POST" action="{{ $attribute->exists ? route('attributes.update', $attribute) : route('attributes.store') }}">
    @csrf @if($attribute->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="code">Código</label><input id="code" name="code" value="{{ old('code', $attribute->code) }}" maxlength="50" required><span class="help">Se guarda en mayúsculas y no se reutiliza al desactivar.</span></div>
        <div class="field"><label for="name">Nombre</label><input id="name" name="name" value="{{ old('name', $attribute->name) }}" maxlength="100" required></div>
        <div class="field"><label for="data_type">Tipo de dato</label><select id="data_type" name="data_type" @disabled($hasAssociations)>@foreach(\App\Models\CatalogAttribute::TYPES as $code => $label)<option value="{{ $code }}" @selected(old('data_type', $attribute->data_type ?? 'text') === $code)>{{ $label }}</option>@endforeach</select>@if($hasAssociations)<input type="hidden" name="data_type" value="{{ $attribute->data_type }}"><span class="help">El tipo y la unidad se conservan desde la primera asociación.</span>@endif</div>
        <div class="field"><label for="measurement_unit_id">Unidad técnica (opcional)</label><select id="measurement_unit_id" name="measurement_unit_id" @disabled($hasAssociations)><option value="">Sin unidad</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('measurement_unit_id', $attribute->measurement_unit_id) === (string) $unit->id)>{{ $unit->name }} ({{ $unit->symbol }}){{ $unit->is_active ? '' : ' — Inactiva' }}</option>@endforeach</select>@if($hasAssociations)<input type="hidden" name="measurement_unit_id" value="{{ $attribute->measurement_unit_id }}">@endif<span class="help">Solo se admite en atributos decimales. No se realizan conversiones automáticas.</span></div>
        <div class="field"><label for="help_text">Texto de ayuda (opcional)</label><textarea id="help_text" name="help_text" maxlength="255">{{ old('help_text', $attribute->help_text) }}</textarea></div>
        <div class="field"><label for="is_active">Estado</label>@if($attribute->exists && ! auth()->user()->can('changeStatus', $attribute))<input id="is_active" value="{{ $attribute->is_active ? 'Activo' : 'Inactivo' }}" disabled><input type="hidden" name="is_active" value="{{ (int) $attribute->is_active }}">@else<select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $attribute->is_active) === '1')>Activo</option><option value="0" @selected((string) old('is_active', (int) $attribute->is_active) === '0')>Inactivo</option></select>@endif<span class="help">Antes de desactivar el atributo, desactiva sus asociaciones activas.</span></div>
    </div>
    <div class="actions"><button type="submit">Guardar atributo</button><a href="{{ route('attributes.index') }}">Cancelar</a></div>
</form></section>
@endsection
