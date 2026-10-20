@extends('layouts.app')
@section('title','Preparar corrección')
@section('content')
<h1>{{ $kind==='adjustment'?'Preparar ajuste por conteo':'Preparar compensación total' }}</h1>
<form method="POST" action="{{ $document->exists?route('corrections.update',[$kind,$document->id]):route('corrections.store',$kind) }}" id="correction-form">@csrf @if($document->exists)@method('PUT')<input type="hidden" name="lock_version" value="{{ $document->lock_version }}">@endif
<label>Fecha del documento<input required type="date" name="document_date" value="{{ is_string(old('document_date'))?old('document_date'):($document->document_date?->format('Y-m-d')??now()->toDateString()) }}"></label>
<label>Motivo<textarea name="reason" required maxlength="1000">{{ is_string(old('reason'))?old('reason'):$document->reason }}</textarea></label>
@if($kind==='compensation')
<p>Se incluirán todos los movimientos del documento. No se anulan dependencias automáticamente.</p>
<label>Tipo de documento<select name="source_kind" required>@foreach(['inventory-documents'=>'Remito o apertura','deliveries'=>'Entrega','loans'=>'Préstamo','returns'=>'Devolución','corrections'=>'Ajuste ejecutado'] as $code=>$label)<option value="{{ $code }}" @selected(old('source_kind',$document->source_kind)===$code)>{{ $label }}</option>@endforeach</select></label>
<label>Número de registro del documento original<input type="number" min="1" required name="source_id" value="{{ is_scalar(old('source_id'))?old('source_id'):$document->source_id }}"></label>
@else
<p>Indica la cantidad total contada. Para productos individuales selecciona solo los identificadores que se incorporan o retiran por la diferencia.</p>
<div id="correction-lines">@foreach($rows as $i=>$row)<fieldset class="card correction-line"><legend>Detalle {{ $i+1 }}</legend>
<label>Producto<select name="lines[{{ $i }}][product_id]" required>@foreach($products as $p)<option value="{{ $p->id }}" @selected(($row['product_id']??null)==$p->id)>{{ $p->internal_code }} · {{ $p->name }}</option>@endforeach</select></label>
<label>Ubicación<select name="lines[{{ $i }}][location_id]" required>@foreach($locations as $l)<option value="{{ $l->id }}" @selected(($row['location_id']??null)==$l->id)>{{ $l->code }} · {{ $l->name }}</option>@endforeach</select></label>
<label>Cantidad contada<input required inputmode="decimal" name="lines[{{ $i }}][counted_quantity]" value="{{ $row['counted_quantity']??'' }}"></label>
<input type="hidden" name="lines[{{ $i }}][unit_ids]" value="">
<label>Identificadores afectados (selección múltiple)<select multiple name="lines[{{ $i }}][unit_ids][]">@foreach($units as $u)<option value="{{ $u->id }}" @selected(in_array($u->id,$row['unit_ids']??[]))>{{ $u->identifier }} · {{ $u->product->internal_code }}</option>@endforeach</select></label>
</fieldset>@endforeach</div><input type="hidden" name="expected_line_count" id="line-count" value="{{ count($rows) }}"><button type="button" id="add-line" class="secondary">Añadir detalle</button>
@endif
<button type="submit">Guardar borrador</button></form>
@if($kind==='adjustment')<script>
document.getElementById('add-line').addEventListener('click',()=>{const area=document.getElementById('correction-lines'),n=area.children.length;if(n>=100)return;const row=area.firstElementChild.cloneNode(true);row.querySelector('legend').textContent='Detalle '+(n+1);row.querySelectorAll('[name]').forEach(e=>{e.name=e.name.replace(/lines\[\d+\]/,'lines['+n+']');if(e.tagName==='INPUT')e.value='';if(e.multiple)Array.from(e.options).forEach(o=>o.selected=false);});area.append(row);document.getElementById('line-count').value=n+1;});
</script>@endif
@endsection
