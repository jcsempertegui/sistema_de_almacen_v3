@extends('layouts.app')
@section('title', $unidad->exists ? 'Editar unidad individualizada' : 'Nueva unidad individualizada')
@section('content')
<h1>{{ $unidad->exists ? 'Editar unidad individualizada' : 'Nueva unidad individualizada' }}</h1>
<section class="card"><form method="POST" action="{{ $unidad->exists ? route('unidades-individuales.update', $unidad) : route('unidades-individuales.store') }}">
    @csrf @if($unidad->exists) @method('PUT')<input type="hidden" name="version_bloqueo" value="{{ old('version_bloqueo', $unidad->version_bloqueo) }}"> @endif
    <div class="form-grid">
        <div class="field"><label for="identificador">Identificador interno</label><input id="identificador" name="identificador" value="{{ $unidad->exists ? $unidad->identificador : old('identificador') }}" maxlength="64" required @readonly($unidad->exists)></div>
        <div class="field"><label for="producto_id">Producto</label>
        @if($unidad->exists)<input value="{{ $unidad->producto->codigo_interno }} · {{ $unidad->producto->nombre }}" id="producto_id" readonly><input type="hidden" name="producto_id" value="{{ $unidad->producto_id }}">
        @else<select id="producto_id" name="producto_id" required><option value="">Selecciona un producto individual</option>@foreach($productos as $producto)<option value="{{ $producto->id }}" @selected((string) old('producto_id', $unidad->producto_id) === (string) $producto->id)>{{ $producto->codigo_interno }} · {{ $producto->nombre }}</option>@endforeach</select>@endif</div>
        <div class="field"><label for="numero_serie">Número de serie (opcional)</label><input id="numero_serie" name="numero_serie" value="{{ $unidad->numero_serie !== null ? $unidad->numero_serie : old('numero_serie') }}" maxlength="100" @readonly($unidad->exists && $unidad->numero_serie !== null)><span class="help">Se conservan mayúsculas y minúsculas. Una serie informada no se sustituye.</span></div>
        <div class="field"><label for="ubicacion_id">Ubicación inicial (opcional)</label>
        @if($unidad->exists)<input id="ubicacion_id" value="{{ $unidad->ubicacion_id ? ($paths[$unidad->ubicacion_id] ?? $unidad->ubicacion->codigo) : 'Sin ubicación inicial' }}" readonly><input type="hidden" name="ubicacion_id" value="{{ $unidad->ubicacion_id }}">
        @else<select id="ubicacion_id" name="ubicacion_id"><option value="">Sin ubicación inicial</option>@foreach($links as $link)<option value="{{ $link->ubicacion_id }}" data-producto="{{ $link->producto_id }}" @selected((string) old('ubicacion_id') === (string) $link->ubicacion_id && (string) old('producto_id', $unidad->producto_id) === (string) $link->producto_id)>{{ $paths[$link->ubicacion_id] }} · {{ $link->producto->codigo_interno }}</option>@endforeach</select>@endif</div>
        <div class="field"><label for="activo">Estado</label>@if($unidad->exists && ! auth()->user()->can('changeStatus', $unidad))<input id="activo" value="{{ $unidad->activo ? 'Activa' : 'Inactiva' }}" readonly><input type="hidden" name="activo" value="{{ (int) $unidad->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $unidad->activo) === '1')>Activa</option><option value="0" @selected((string) old('activo', (int) $unidad->activo) === '0')>Inactiva</option></select>@endif</div>
        <div class="field"><label for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones" maxlength="10000">{{ old('observaciones', $unidad->observaciones) }}</textarea></div>
    </div>
    <p class="help">La ubicación inicial se conserva después del registro. Registrar esta identidad no incrementa existencias físicas.</p>
    <div class="actions"><button type="submit">Guardar unidad</button>@can('viewAny', \App\Models\UnidadIndividual::class)<a href="{{ route('unidades-individuales.index') }}">Volver al listado</a>@endcan</div>
</form></section>
@unless($unidad->exists)
<script>
const productSelect = document.getElementById('producto_id');
const locationSelect = document.getElementById('ubicacion_id');
function filterInitialLocations() {
    for (const option of locationSelect.options) {
        if (!option.value) continue;
        option.hidden = option.dataset.producto !== productSelect.value;
        option.disabled = option.hidden;
        if (option.hidden && option.selected) locationSelect.value = '';
    }
}
productSelect.addEventListener('change', filterInitialLocations);
filterInitialLocations();
</script>
@endunless
@endsection
