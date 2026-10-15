@extends('layouts.app')
@section('title', 'Revisar producto')
@section('content')
<h1>Revisar producto</h1>
<section class="card"><h2>{{ $draft->internal_code }} · {{ $draft->name }}</h2><p>{{ $draft->brand }} {{ $draft->manufacturer_reference }}</p><p>{{ \App\Models\Product::MODES[$draft->control_mode] }}{{ $draft->is_loanable ? ' · Prestable' : '' }}</p>
<ul>@foreach($definitions as $definition) @if(($values[$definition->id] ?? null) !== null)<li>{{ $definition->attribute->name }}: {{ $definition->attribute->data_type === 'boolean' ? ($values[$definition->id] ? 'Sí' : 'No') : $values[$definition->id] }}</li>@endif @endforeach</ul></section>
<section class="card"><h2>Revisión de posibles duplicados</h2>
@if($hasCandidates)
    <p>Se encontraron características de identificación coincidentes. Revisa los candidatos; esta comparación no certifica equivalencia técnica.</p>
    @foreach($candidates as $candidate)<article><h3><a href="{{ route('products.show', $candidate) }}">{{ $candidate->internal_code }} · {{ $candidate->name }}</a> · {{ $candidate->is_active ? 'Activo' : 'Inactivo' }}</h3><p>Marca: {{ $candidate->brand ?: 'Sin informar' }}. Referencia: {{ $candidate->manufacturer_reference ?: 'Sin informar' }}.</p><ul>@foreach($candidate->attributeValues as $row)<li>{{ $definitions->firstWhere('id', $row->category_attribute_id)?->attribute->name }}: {{ $row->displayValue() }}</li>@endforeach</ul></article>@endforeach
@else<p>No se encontraron candidatos con todos los identificadores técnicos coincidentes. Si faltan identificadores o no están configurados, la comparación es insuficiente y requiere revisión humana.</p>@endif
<form method="POST" action="{{ $draft->exists ? route('products.update', $draft) : route('products.store') }}">@csrf @if($draft->exists) @method('PUT') @endif
    @foreach($data as $field => $value) @if($field !== 'duplicate_token')
        @if(is_array($value)) @foreach($value as $key => $item)<input type="hidden" name="{{ $field }}[{{ $key }}]" value="{{ is_bool($item) ? (int) $item : $item }}">@endforeach
        @else<input type="hidden" name="{{ $field }}" value="{{ is_bool($value) ? (int) $value : $value }}">@endif
    @endif @endforeach
    <input type="hidden" name="duplicate_token" value="{{ $token }}">
    <div class="actions"><button>{{ $hasCandidates ? 'Revisado: guardar producto distinto' : 'Confirmar y guardar' }}</button><button type="button" class="secondary" onclick="history.back()">Corregir</button></div>
</form></section>
@endsection
