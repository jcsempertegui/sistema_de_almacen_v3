<div class="table-wrap report-table-wrap" tabindex="0" role="region" aria-label="Detalle del informe, desplazable horizontalmente">
<table class="report-table"><caption class="sr-only">{{ $label }} · Detalle de la consulta</caption><thead><tr>@foreach($sections as $section => $fields)<th scope="col">{{ $section }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)
    <tr>
    @foreach($sections as $section => $fields)
        <td>
        @foreach($fields as $field)
            @if($field !== 'unidad' && ($row->$field ?? null) !== null && ($row->$field ?? '') !== '')
                @php($value = $catalog->cell($field, $row->$field))
                @php($documentUrl = in_array($field, ['documento', 'prestamo']) ? $catalog->documentUrl($value, $documentPermissions, $row->tipo ?? $row->_correction_kind ?? null) : null)
                <div @class(['report-cell-value', 'report-cell-primary' => $loop->first, 'report-cell-number' => in_array($field, ['cantidad', 'saldo', 'conteo', 'saldo_observado'])])>
                    @if(!$loop->first)<span class="report-cell-label">{{ $columns[$field] }}:</span>@endif
                    @if($documentUrl)<a href="{{ $documentUrl }}">{{ $value }}</a>@elseif(in_array($field, ['estado', 'situacion', 'sentido']))<span @class(['report-badge', 'report-badge-alert' => $value === 'Vencido', 'report-badge-positive' => in_array($value, ['Confirmado', 'Devuelto', 'Ingreso'])])>{{ $value }}</span>@else<span>{{ $value }}</span>@endif
                    @if(in_array($field, ['cantidad', 'saldo']) && isset($row->unidad))<span class="report-unit">{{ $row->unidad }}</span>@endif
                </div>
            @endif
        @endforeach
        </td>
    @endforeach
    </tr>
@empty
    <tr><td colspan="{{ count($sections) }}"><div class="report-empty"><x-icon name="chart" /><strong>No hay resultados para esta consulta</strong><p>Prueba otro período o amplía los filtros seleccionados.</p></div></td></tr>
@endforelse
</tbody></table></div>
