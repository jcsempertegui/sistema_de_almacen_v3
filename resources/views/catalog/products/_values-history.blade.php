@forelse($product->attributeValues as $row)
<article><h3>{{ $row->categoryAttribute->attribute->name }}</h3><p>{{ $row->displayValue() }} {{ $row->categoryAttribute->attribute->measurementUnit?->symbol }} · {{ $row->is_active ? 'Valor activo' : 'Valor histórico inactivo' }}</p>
<details><summary>Ver revisiones</summary><div class="table-wrap"><table><thead><tr><th>Revisión</th><th>Valor</th><th>Estado</th><th>Responsable</th><th>Fecha</th></tr></thead><tbody>
@foreach($row->revisions as $revision)<tr><td>{{ $revision->revision }}</td><td>{{ $revision->value_boolean !== null ? ($revision->value_boolean ? 'Sí' : 'No') : ($revision->value_date?->format('Y-m-d') ?? $revision->value_decimal ?? $revision->value_text) }}</td><td>{{ $revision->is_active ? 'Activo' : 'Inactivo' }}</td><td>{{ $revision->creator->name }}</td><td>{{ $revision->created_at }}</td></tr>@endforeach
</tbody></table></div></details></article>
@empty<p>No hay valores técnicos registrados.</p>@endforelse
