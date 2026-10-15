<div class="form-grid">
@forelse($definitions as $definition)
    @php
        $attribute = $definition->attribute;
        $row = $existing->get($definition->id);
        $available = app(\App\Services\ProductAttributeValidator::class)->available($definition);
        $value = old('values.'.$definition->id, $row?->is_active ? $row->getRawOriginal('value_'.$attribute->data_type) : null);
    @endphp
    @if($available)
    <div class="field"><label for="attribute-{{ $definition->id }}">{{ $attribute->name }}{{ $definition->is_required ? ' · Obligatorio al activar' : '' }}{{ $definition->is_identity ? ' · Identificación técnica' : '' }}</label>
    @if($attribute->data_type === 'boolean')
        <select id="attribute-{{ $definition->id }}" name="values[{{ $definition->id }}]"><option value="">Sin informar</option><option value="1" @selected($value !== null && (string) $value === '1')>Sí</option><option value="0" @selected($value !== null && (string) $value === '0')>No</option></select>
    @else
        <input id="attribute-{{ $definition->id }}" name="values[{{ $definition->id }}]" type="{{ $attribute->data_type === 'date' ? 'date' : 'text' }}" @if($attribute->data_type === 'decimal') inputmode="decimal" @endif @if($attribute->data_type === 'text') maxlength="255" @endif value="{{ $value }}">
    @endif
    <span class="help">{{ $attribute->help_text }} @if($attribute->measurementUnit) Unidad: {{ $attribute->measurementUnit->symbol }}. Precisión: {{ $attribute->measurementUnit->decimal_places }} decimales. @endif @if($definition->min_value !== null) Mínimo: {{ $definition->min_value }}. @endif @if($definition->max_value !== null) Máximo: {{ $definition->max_value }}. @endif</span></div>
    @elseif($row)
        <div class="field"><label>{{ $attribute->name }} · Configuración histórica</label><input disabled value="{{ $row->displayValue() }}"><span class="help">El valor se conserva sin modificación.</span></div>
    @endif
@empty<p>La categoría no tiene atributos técnicos configurados.</p>@endforelse
</div>
