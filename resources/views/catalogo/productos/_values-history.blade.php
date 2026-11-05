@forelse($producto->valoresAtributos as $row)
<article><h3>{{ $row->atributoCategoria->atributo->nombre }}</h3><p>{{ $row->valorPresentacion() }} {{ $row->atributoCategoria->atributo->unidadMedida?->simbolo }} · {{ $row->activo ? 'Valor activo' : 'Valor histórico inactivo' }}</p>
<details><summary>Ver revisiones</summary><div class="table-wrap"><table><thead><tr><th>Revisión</th><th>Valor</th><th>Estado</th><th>Responsable</th><th>Fecha</th></tr></thead><tbody>
@foreach($row->revisiones as $revision)<tr><td>{{ $revision->revision }}</td><td>{{ $revision->valor_booleano !== null ? ($revision->valor_booleano ? 'Sí' : 'No') : ($revision->valor_fecha?->format('Y-m-d') ?? $revision->valor_decimal ?? $revision->valor_texto) }}</td><td>{{ $revision->activo ? 'Activo' : 'Inactivo' }}</td><td>{{ $revision->creador->nombre }}</td><td>{{ $revision->fecha_creacion }}</td></tr>@endforeach
</tbody></table></div></details></article>
@empty<p>No hay valores técnicos registrados.</p>@endforelse
