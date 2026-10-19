<fieldset><legend>Línea de la operación</legend>
@if($kind==='deliveries')
<label>Producto<select name="lines[{{ $index }}][product_id]" required><option value="">Selecciona un producto</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string)($row['product_id']??'')===(string)$product->id)>{{ $product->internal_code }} · {{ $product->name }} ({{ $product->control_mode==='individual'?'individual':'por cantidad' }})</option>@endforeach</select></label>
<label>Cantidad<input name="lines[{{ $index }}][quantity]" inputmode="decimal" required value="{{ $row['quantity']??'' }}"></label>
<label>Identidades, solo para control individual<select name="lines[{{ $index }}][unit_ids][]" multiple size="4">@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(in_array($unit->id,$row['unit_ids']??[]))>{{ $unit->identifier }} · {{ $unit->product->internal_code }}</option>@endforeach</select></label>
@elseif($kind==='loans')
<label>Herramienta individual prestable<select name="lines[{{ $index }}][individual_unit_id]" required><option value="">Selecciona una herramienta</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)($row['individual_unit_id']??'')===(string)$unit->id)>{{ $unit->identifier }} · {{ $unit->product->internal_code }}</option>@endforeach</select></label>
@else
<label>Ítem pendiente del préstamo<select name="lines[{{ $index }}][loan_item_id]" required><option value="">Selecciona la herramienta recibida</option>@foreach($loanItems as $item)<option data-loan="{{ $item->loan_id }}" value="{{ $item->id }}" @selected((string)($row['loan_item_id']??'')===(string)$item->id)>{{ $item->unit->identifier }} · {{ $item->product->internal_code }} · ítem {{ $item->id }}</option>@endforeach</select></label>
@endif
<label>{{ $kind==='returns'?'Ubicación de recepción':'Ubicación de retiro' }}<select name="lines[{{ $index }}][location_id]" required><option value="">Selecciona manualmente una ubicación</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)($row['location_id']??'')===(string)$location->id)>{{ $location->code }} · {{ $location->name }}</option>@endforeach</select></label>
<button type="button" data-remove class="secondary">Quitar línea</button></fieldset>
