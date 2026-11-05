@extends('layouts.app')
@section('title','Preparar corrección')
@section('content')
<h1>{{ $clase==='adjustment'?'Preparar ajuste por conteo':'Preparar compensación total' }}</h1>
<form method="POST" action="{{ $documento->exists?route('corrections.update',[$clase,$documento->id]):route('corrections.store',$clase) }}" id="correccion-form">@csrf @if($documento->exists)@method('PUT')<input type="hidden" name="version_bloqueo" value="{{ $documento->version_bloqueo }}">@endif
<label>Fecha del documento<input required type="date" name="fecha_documento" value="{{ is_string(old('fecha_documento'))?old('fecha_documento'):($documento->fecha_documento?->format('Y-m-d')??now()->toDateString()) }}"></label>
<label>Motivo<textarea name="motivo" required maxlength="1000">{{ is_string(old('motivo'))?old('motivo'):$documento->motivo }}</textarea></label>
@if($clase==='compensation')
<p>Se incluirán todos los movimientos del documento. No se anulan dependencias automáticamente.</p>
<label>Tipo de documento<select name="tipo_origen" required>@foreach(['inventory-documents'=>'Remito o apertura','deliveries'=>'Entrega','loans'=>'Préstamo','returns'=>'Devolución','corrections'=>'Ajuste ejecutado'] as $codigo=>$label)<option value="{{ $codigo }}" @selected(old('tipo_origen',$documento->tipo_origen)===$codigo)>{{ $label }}</option>@endforeach</select></label>
<label>Número de registro del documento original<input type="number" min="1" required name="origen_id" value="{{ is_scalar(old('origen_id'))?old('origen_id'):$documento->origen_id }}"></label>
@else
<p>Indica la cantidad total contada. Para productos individuales selecciona solo los identificadores que se incorporan o retiran por la diferencia.</p>
<div id="correccion-lineas">@foreach($rows as $i=>$row)<fieldset class="card correction-line"><legend>Detalle {{ $i+1 }}</legend>
<label>Producto<select name="lineas[{{ $i }}][producto_id]" required>@foreach($productos as $p)<option value="{{ $p->id }}" @selected(($row['producto_id']??null)==$p->id)>{{ $p->codigo_interno }} · {{ $p->nombre }}</option>@endforeach</select></label>
<label>Ubicación<select name="lineas[{{ $i }}][ubicacion_id]" required>@foreach($ubicaciones as $l)<option value="{{ $l->id }}" @selected(($row['ubicacion_id']??null)==$l->id)>{{ $l->codigo }} · {{ $l->nombre }}</option>@endforeach</select></label>
<label>Cantidad contada<input required inputmode="decimal" name="lineas[{{ $i }}][cantidad_contada]" value="{{ $row['cantidad_contada']??'' }}"></label>
<input type="hidden" name="lineas[{{ $i }}][unit_ids]" value="">
<label>Identificadores afectados (selección múltiple)<select multiple name="lineas[{{ $i }}][unit_ids][]">@foreach($unidades as $u)<option value="{{ $u->id }}" @selected(in_array($u->id,$row['unit_ids']??[]))>{{ $u->identificador }} · {{ $u->producto->codigo_interno }}</option>@endforeach</select></label>
</fieldset>@endforeach</div><input type="hidden" name="expected_line_count" id="line-count" value="{{ count($rows) }}"><button type="button" id="add-line" class="secondary">Añadir detalle</button>
@endif
<button type="submit">Guardar borrador</button></form>
@if($clase==='adjustment')<script>
document.getElementById('add-line').addEventListener('click',()=>{const area=document.getElementById('correccion-lineas'),n=area.children.length;if(n>=100)return;const row=area.firstElementChild.cloneNode(true);row.querySelectorAll('.sga-selector').forEach(control=>control.remove());row.querySelector('legend').textContent='Detalle '+(n+1);row.querySelectorAll('[name]').forEach(e=>{e.name=e.name.replace(/lineas\[\d+\]/,'lineas['+n+']');if(e.tagName==='INPUT')e.value='';if(e.multiple)Array.from(e.options).forEach(o=>o.selected=false);});area.append(row);document.getElementById('line-count').value=n+1;});
</script>@endif
@endsection
