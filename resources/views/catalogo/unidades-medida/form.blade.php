@extends('layouts.app')
@section('title', $unidad->exists ? 'Editar unidad de medida' : 'Nueva unidad de medida')
@section('content')
<h1>{{ $unidad->exists ? 'Editar unidad de medida' : 'Nueva unidad de medida' }}</h1>
<section class="card"><form method="POST" action="{{ $unidad->exists ? route('unidades-medida.update', $unidad) : route('unidades-medida.store') }}">
    @csrf @if($unidad->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="codigo">Código</label><input id="codigo" name="codigo" value="{{ old('codigo', $unidad->codigo) }}" maxlength="30" required></div>
        <div class="field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre', $unidad->nombre) }}" maxlength="100" required></div>
        <div class="field"><label for="simbolo">Símbolo</label><input id="simbolo" name="simbolo" value="{{ old('simbolo', $unidad->simbolo) }}" maxlength="20" required aria-describedby="simbolo-help"><span id="simbolo-help" class="help">Se conservan mayúsculas y minúsculas. Ejemplos: unid., m, kg, L.</span></div>
        <div class="field"><label for="dimension">Dimensión</label><select id="dimension" name="dimension" required><option value="">Selecciona una dimensión</option>@foreach(\App\Models\UnidadMedida::DIMENSIONS as $codigo => $label)<option value="{{ $codigo }}" @selected(old('dimension', $unidad->dimension) === $codigo)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="decimales">Precisión decimal</label><input id="decimales" name="decimales" type="number" min="0" max="6" step="1" value="{{ old('decimales', $unidad->decimales) }}" required></div>
        <div class="field"><label for="activo">Estado</label>@if($unidad->exists && ! auth()->user()->can('changeStatus', $unidad))<input id="activo" value="{{ $unidad->activo ? 'Activa' : 'Inactiva' }}" disabled><input type="hidden" name="activo" value="{{ (int) $unidad->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $unidad->activo) === '1')>Activa</option><option value="0" @selected((string) old('activo', (int) $unidad->activo) === '0')>Inactiva</option></select>@endif</div>
    </div>
    <p class="help">Si la unidad tiene referencias, conserva su código, símbolo, dimensión y precisión. Los cambios de significado requieren otra unidad.</p>
    <div class="actions"><button type="submit">Guardar unidad de medida</button><a href="{{ route('unidades-medida.index') }}">Cancelar</a></div>
</form></section>
@endsection
