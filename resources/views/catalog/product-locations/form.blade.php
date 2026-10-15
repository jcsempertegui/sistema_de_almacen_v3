@extends('layouts.app')
@section('title', 'Asociar ubicación')
@section('content')
<h1>Asociar ubicación a {{ $product->internal_code }}</h1>
<section class="card"><form method="POST" action="{{ route('product-locations.store', $product) }}">@csrf
<input type="hidden" name="lock_version" value="{{ old('lock_version', $product->lock_version) }}"><input type="hidden" name="is_active" value="1">
<div class="field"><label for="location_id">Ubicación activa y almacenable</label><select id="location_id" name="location_id" required><option value="">Seleccionar</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $paths[$location->id] }}</option>@endforeach</select></div>
<p class="help">Las asociaciones existentes deben reactivarse. Esta operación no registra cantidades.</p><button>Guardar asociación</button>
</form></section>
@endsection
