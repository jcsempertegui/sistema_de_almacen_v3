@php
    $headerNames = ['Documento', 'Trabajador', 'Contraparte', 'Responsables', 'Observaciones', 'Justificación', 'Autorización más reciente'];
    $headerSections = array_intersect_key($sections, array_flip($headerNames));
    $detailSections = ['Línea / ítem' => ['linea']] + array_diff_key($sections, $headerSections);
@endphp
@foreach($rows->groupBy('documento') as $code => $documentRows)
    @php($first = $documentRows->first())
    <article class="report-print-document">
        <h3>{{ $code }}</h3>
        <dl class="report-document-summary">
        @foreach(array_unique(array_merge(...array_values($headerSections))) as $field)
            @if(!in_array($field, ['documento', 'linea']) && ($first->$field ?? null) !== null && ($first->$field ?? '') !== '')
                <div @class(['report-document-wide' => in_array($field, ['notas', 'motivo'])])><dt>{{ $columns[$field] }}</dt><dd>{{ $catalog->cell($field, $first->$field) }}</dd></div>
            @endif
        @endforeach
        </dl>
        @include('control.report-table', ['rows' => $documentRows, 'sections' => $detailSections])
    </article>
@endforeach
