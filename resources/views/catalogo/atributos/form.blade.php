@extends('layouts.app')
@section('title', $atributo->exists ? 'Editar atributo técnico' : 'Nuevo atributo técnico')
@section('content')
<h1>{{ $atributo->exists ? 'Editar atributo técnico' : 'Nuevo atributo técnico' }}</h1>
@php($hasAssociations = $atributo->exists && $atributo->atributosCategoria()->exists())
<section class="card"><form method="POST" action="{{ $atributo->exists ? route('atributos.update', $atributo) : route('atributos.store') }}">
    @csrf @if($atributo->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="codigo">Código</label><input id="codigo" name="codigo" value="{{ old('codigo', $atributo->codigo) }}" maxlength="50" required><span class="help">Se guarda en mayúsculas y no se reutiliza al desactivar.</span></div>
        <div class="field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre', $atributo->nombre) }}" maxlength="100" required></div>
        <div class="field"><label for="tipo_dato">Tipo de dato</label><select id="tipo_dato" name="tipo_dato" @disabled($hasAssociations)>@foreach(\App\Models\AtributoCatalogo::TYPES as $codigo => $label)<option value="{{ $codigo }}" @selected(old('tipo_dato', $atributo->tipo_dato ?? 'text') === $codigo)>{{ $label }}</option>@endforeach</select>@if($hasAssociations)<input type="hidden" name="tipo_dato" value="{{ $atributo->tipo_dato }}"><span class="help">El tipo y la unidad se conservan desde la primera asociación.</span>@endif</div>
        <div class="field"><label for="unidad_medida_id">Unidad técnica (opcional)</label><select id="unidad_medida_id" name="unidad_medida_id" @disabled($hasAssociations)><option value="">Sin unidad</option>@foreach($unidades as $unidad)<option value="{{ $unidad->id }}" @selected((string) old('unidad_medida_id', $atributo->unidad_medida_id) === (string) $unidad->id)>{{ $unidad->nombre }} ({{ $unidad->simbolo }}){{ $unidad->activo ? '' : ' — Inactiva' }}</option>@endforeach</select>@if($hasAssociations)<input type="hidden" name="unidad_medida_id" value="{{ $atributo->unidad_medida_id }}">@endif<span class="help">Solo se admite en atributos decimales. No se realizan conversiones automáticas.</span></div>
        <div class="field"><label for="texto_ayuda">Texto de ayuda (opcional)</label><textarea id="texto_ayuda" name="texto_ayuda" maxlength="255">{{ old('texto_ayuda', $atributo->texto_ayuda) }}</textarea></div>
        <div class="field"><label for="activo">Estado</label>@if($atributo->exists && ! auth()->user()->can('changeStatus', $atributo))<input id="activo" value="{{ $atributo->activo ? 'Activo' : 'Inactivo' }}" disabled><input type="hidden" name="activo" value="{{ (int) $atributo->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $atributo->activo) === '1')>Activo</option><option value="0" @selected((string) old('activo', (int) $atributo->activo) === '0')>Inactivo</option></select>@endif<span class="help">Antes de desactivar el atributo, desactiva sus asociaciones activas.</span></div>
    </div>
    <div class="actions"><button type="submit">Guardar atributo</button><a href="{{ route('atributos.index') }}">Cancelar</a></div>
</form></section>
@endsection
