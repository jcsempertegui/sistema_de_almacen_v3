@extends('layouts.app')
@section('title', $unit->exists ? 'Editar unidad de medida' : 'Nueva unidad de medida')
@section('content')
<h1>{{ $unit->exists ? 'Editar unidad de medida' : 'Nueva unidad de medida' }}</h1>
<section class="card"><form method="POST" action="{{ $unit->exists ? route('measurement-units.update', $unit) : route('measurement-units.store') }}">
    @csrf @if($unit->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="code">Código</label><input id="code" name="code" value="{{ old('code', $unit->code) }}" maxlength="30" required></div>
        <div class="field"><label for="name">Nombre</label><input id="name" name="name" value="{{ old('name', $unit->name) }}" maxlength="100" required></div>
        <div class="field"><label for="symbol">Símbolo</label><input id="symbol" name="symbol" value="{{ old('symbol', $unit->symbol) }}" maxlength="20" required aria-describedby="symbol-help"><span id="symbol-help" class="help">Se conservan mayúsculas y minúsculas. Ejemplos: unid., m, kg, L.</span></div>
        <div class="field"><label for="dimension">Dimensión</label><select id="dimension" name="dimension" required><option value="">Selecciona una dimensión</option>@foreach(\App\Models\MeasurementUnit::DIMENSIONS as $code => $label)<option value="{{ $code }}" @selected(old('dimension', $unit->dimension) === $code)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="decimal_places">Precisión decimal</label><input id="decimal_places" name="decimal_places" type="number" min="0" max="6" step="1" value="{{ old('decimal_places', $unit->decimal_places) }}" required></div>
        <div class="field"><label for="is_active">Estado</label>@if($unit->exists && ! auth()->user()->can('changeStatus', $unit))<input id="is_active" value="{{ $unit->is_active ? 'Activa' : 'Inactiva' }}" disabled><input type="hidden" name="is_active" value="{{ (int) $unit->is_active }}">@else<select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $unit->is_active) === '1')>Activa</option><option value="0" @selected((string) old('is_active', (int) $unit->is_active) === '0')>Inactiva</option></select>@endif</div>
    </div>
    <p class="help">Si la unidad tiene referencias, conserva su código, símbolo, dimensión y precisión. Los cambios de significado requieren otra unidad.</p>
    <div class="actions"><button type="submit">Guardar unidad de medida</button><a href="{{ route('measurement-units.index') }}">Cancelar</a></div>
</form></section>
@endsection
