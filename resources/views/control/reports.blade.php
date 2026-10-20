@extends('layouts.app')
@section('title',$label)
@section('content')
<h1>Reporte · {{ $label }}</h1><p>Fechas de registro físico para movimientos; fechas documentales para documentos. El corte de existencias utiliza todos los movimientos hasta «Hasta» inclusive. Disponibilidad muestra presencia y custodia actuales; revisa también el estado del catálogo.</p>
<form method="GET">
@if(!in_array($kind,['stock','availability']))<label>Desde<input type="date" name="from" value="{{ request('from') }}"></label>@endif
@if($kind!=='availability')<label>Hasta<input type="date" name="to" value="{{ request('to') }}"></label>@endif
@if(in_array($kind,['stock','availability','movements','responsibilities']))<label>Producto (número de registro)<input type="number" min="1" name="product_id" value="{{ request('product_id') }}"></label>@endif
@if(in_array($kind,['stock','availability','movements']))<label>Ubicación (número de registro)<input type="number" min="1" name="location_id" value="{{ request('location_id') }}"></label>@endif
@if(in_array($kind,['deliveries','loans','returns','responsibilities']))<label>Trabajador (número de registro)<input type="number" min="1" name="worker_id" value="{{ request('worker_id') }}"></label>@endif
<button>Consultar</button></form>
@can(app(\App\Services\ControlReports::class)->permission($kind,'export'))<a href="{{ request()->fullUrlWithQuery(['format'=>'csv','page'=>null]) }}">Descargar CSV</a>@endcan <button type="button" onclick="window.print()" class="secondary">Imprimir</button>
<div class="table-wrap"><table><thead><tr>@foreach(array_keys((array)$rows->first()) as $column)<th>{{ str_replace('_',' ',$column) }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>
@foreach((array)$row as $column=>$value)<td>
@if($column==='estado'){{ __('control.states.'.$value, [], 'es') }}
@elseif($column==='tipo'){{ __('control.kinds.'.$value, [], 'es') }}
@elseif($column==='sentido'){{ $value==='in'?'Ingreso':'Egreso' }}
@elseif(in_array($column,['presencia_fisica','identidad_activa','producto_activo','trabajador_activo'])){{ $value?'Sí':'No' }}
@else{{ $value??'—' }}
@endif
</td>@endforeach
</tr>@empty<tr><td>Sin registros para los filtros elegidos.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}
@endsection
