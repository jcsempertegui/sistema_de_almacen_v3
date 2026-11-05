<div class="form-grid">
@forelse($definitions as $definition)
    @php
        $atributo = $definition->atributo;
        $row = $existing->get($definition->id);
        $available = app(\App\Services\ValidadorAtributosProducto::class)->available($definition);
        $value = old('values.'.$definition->id, $row?->activo ? $row->getRawOriginal(\App\Models\ValorAtributoProducto::columnaParaTipo($atributo->tipo_dato)) : null);
    @endphp
    @if($available)
    <div class="field"><label for="atributo-{{ $definition->id }}">{{ $atributo->nombre }}{{ $definition->obligatorio ? ' · Obligatorio al activar' : '' }}{{ $definition->identificacion_tecnica ? ' · Identificación técnica' : '' }}</label>
    @if($atributo->tipo_dato === 'boolean')
        <select id="atributo-{{ $definition->id }}" name="values[{{ $definition->id }}]"><option value="">Sin informar</option><option value="1" @selected($value !== null && (string) $value === '1')>Sí</option><option value="0" @selected($value !== null && (string) $value === '0')>No</option></select>
    @else
        <input id="atributo-{{ $definition->id }}" name="values[{{ $definition->id }}]" type="{{ $atributo->tipo_dato === 'date' ? 'date' : 'text' }}" @if($atributo->tipo_dato === 'decimal') inputmode="decimal" @endif @if($atributo->tipo_dato === 'text') maxlength="255" @endif value="{{ $value }}">
    @endif
    <span class="help">{{ $atributo->texto_ayuda }} @if($atributo->unidadMedida) Unidad: {{ $atributo->unidadMedida->simbolo }}. Precisión: {{ $atributo->unidadMedida->decimales }} decimales. @endif @if($definition->valor_minimo !== null) Mínimo: {{ $definition->valor_minimo }}. @endif @if($definition->valor_maximo !== null) Máximo: {{ $definition->valor_maximo }}. @endif</span></div>
    @elseif($row)
        <div class="field"><label>{{ $atributo->nombre }} · Configuración histórica</label><input disabled value="{{ $row->valorPresentacion() }}"><span class="help">El valor se conserva sin modificación.</span></div>
    @endif
@empty<p>La categoría no tiene atributos técnicos configurados.</p>@endforelse
</div>
