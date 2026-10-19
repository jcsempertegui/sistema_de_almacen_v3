@extends('layouts.app')
@section('title', $unit->exists ? 'Editar unidad individualizada' : 'Nueva unidad individualizada')
@section('content')
<h1>{{ $unit->exists ? 'Editar unidad individualizada' : 'Nueva unidad individualizada' }}</h1>
<section class="card"><form method="POST" action="{{ $unit->exists ? route('individual-units.update', $unit) : route('individual-units.store') }}">
    @csrf @if($unit->exists) @method('PUT')<input type="hidden" name="lock_version" value="{{ old('lock_version', $unit->lock_version) }}"> @endif
    <div class="form-grid">
        <div class="field"><label for="identifier">Identificador interno</label><input id="identifier" name="identifier" value="{{ $unit->exists ? $unit->identifier : old('identifier') }}" maxlength="64" required @readonly($unit->exists)></div>
        <div class="field"><label for="product_id">Producto</label>
        @if($unit->exists)<input value="{{ $unit->product->internal_code }} · {{ $unit->product->name }}" id="product_id" readonly><input type="hidden" name="product_id" value="{{ $unit->product_id }}">
        @else<select id="product_id" name="product_id" required><option value="">Selecciona un producto individual</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) old('product_id', $unit->product_id) === (string) $product->id)>{{ $product->internal_code }} · {{ $product->name }}</option>@endforeach</select>@endif</div>
        <div class="field"><label for="serial_number">Número de serie (opcional)</label><input id="serial_number" name="serial_number" value="{{ $unit->serial_number !== null ? $unit->serial_number : old('serial_number') }}" maxlength="100" @readonly($unit->exists && $unit->serial_number !== null)><span class="help">Se conservan mayúsculas y minúsculas. Una serie informada no se sustituye.</span></div>
        <div class="field"><label for="location_id">Ubicación inicial (opcional)</label>
        @if($unit->exists)<input id="location_id" value="{{ $unit->location_id ? ($paths[$unit->location_id] ?? $unit->location->code) : 'Sin ubicación inicial' }}" readonly><input type="hidden" name="location_id" value="{{ $unit->location_id }}">
        @else<select id="location_id" name="location_id"><option value="">Sin ubicación inicial</option>@foreach($links as $link)<option value="{{ $link->location_id }}" data-product="{{ $link->product_id }}" @selected((string) old('location_id') === (string) $link->location_id && (string) old('product_id', $unit->product_id) === (string) $link->product_id)>{{ $paths[$link->location_id] }} · {{ $link->product->internal_code }}</option>@endforeach</select>@endif</div>
        <div class="field"><label for="is_active">Estado</label>@if($unit->exists && ! auth()->user()->can('changeStatus', $unit))<input id="is_active" value="{{ $unit->is_active ? 'Activa' : 'Inactiva' }}" readonly><input type="hidden" name="is_active" value="{{ (int) $unit->is_active }}">@else<select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $unit->is_active) === '1')>Activa</option><option value="0" @selected((string) old('is_active', (int) $unit->is_active) === '0')>Inactiva</option></select>@endif</div>
        <div class="field"><label for="notes">Observaciones</label><textarea id="notes" name="notes" maxlength="10000">{{ old('notes', $unit->notes) }}</textarea></div>
    </div>
    <p class="help">La ubicación inicial se conserva después del registro. Registrar esta identidad no incrementa existencias físicas.</p>
    <div class="actions"><button type="submit">Guardar unidad</button>@can('viewAny', \App\Models\IndividualUnit::class)<a href="{{ route('individual-units.index') }}">Volver al listado</a>@endcan</div>
</form></section>
@unless($unit->exists)
<script>
const productSelect = document.getElementById('product_id');
const locationSelect = document.getElementById('location_id');
function filterInitialLocations() {
    for (const option of locationSelect.options) {
        if (!option.value) continue;
        option.hidden = option.dataset.product !== productSelect.value;
        option.disabled = option.hidden;
        if (option.hidden && option.selected) locationSelect.value = '';
    }
}
productSelect.addEventListener('change', filterInitialLocations);
filterInitialLocations();
</script>
@endunless
@endsection
