@extends('layouts.app')
@section('title', 'Revisar producto')
@section('content')
<h1>Revisar producto</h1>
<section class="card"><h2>{{ $draft->codigo_interno }} · {{ $draft->nombre }}</h2><p>{{ $draft->marca }} {{ $draft->referencia_fabricante }}</p><p>{{ \App\Models\Producto::MODES[$draft->modo_control] }}{{ $draft->prestable ? ' · Prestable' : '' }}</p>
<ul>@foreach($definitions as $definition) @if(($values[$definition->id] ?? null) !== null)<li>{{ $definition->atributo->nombre }}: {{ $definition->atributo->tipo_dato === 'boolean' ? ($values[$definition->id] ? 'Sí' : 'No') : $values[$definition->id] }}</li>@endif @endforeach</ul></section>
<section class="card"><h2>Revisión de posibles duplicados</h2>
@if($hasCandidates)
    <p>Se encontraron características de identificación coincidentes. Revisa los candidatos; esta comparación no certifica equivalencia técnica.</p>
    @foreach($candidates as $candidate)<article><h3><a href="{{ route('productos.show', $candidate) }}">{{ $candidate->codigo_interno }} · {{ $candidate->nombre }}</a> · {{ $candidate->activo ? 'Activo' : 'Inactivo' }}</h3><p>Marca: {{ $candidate->marca ?: 'Sin informar' }}. Referencia: {{ $candidate->referencia_fabricante ?: 'Sin informar' }}.</p><ul>@foreach($candidate->valoresAtributos as $row)<li>{{ $definitions->firstWhere('id', $row->atributo_categoria_id)?->atributo->nombre }}: {{ $row->valorPresentacion() }}</li>@endforeach</ul></article>@endforeach
@else<p>No se encontraron candidatos con todos los identificadores técnicos coincidentes. Si faltan identificadores o no están configurados, la comparación es insuficiente y requiere revisión humana.</p>@endif
<form method="POST" action="{{ $draft->exists ? route('productos.update', $draft) : route('productos.store') }}">@csrf @if($draft->exists) @method('PUT') @endif
    @foreach($data as $field => $value) @if($field !== 'duplicate_token')
        @if(is_array($value)) @foreach($value as $key => $item)<input type="hidden" name="{{ $field }}[{{ $key }}]" value="{{ is_bool($item) ? (int) $item : $item }}">@endforeach
        @else<input type="hidden" name="{{ $field }}" value="{{ is_bool($value) ? (int) $value : $value }}">@endif
    @endif @endforeach
    <input type="hidden" name="duplicate_token" value="{{ $token }}">
    <div class="actions"><button>{{ $hasCandidates ? 'Revisado: guardar producto distinto' : 'Confirmar y guardar' }}</button><button type="button" class="secondary" onclick="history.back()">Corregir</button></div>
</form></section>
@endsection
