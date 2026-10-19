@extends('layouts.app')
@section('title', 'Importar apertura CSV')
@section('content')
<h1>Revisar apertura desde CSV</h1><p>Exporta desde Excel como CSV UTF-8 delimitado por punto y coma. El archivo solo referencia catálogo existente. No crea productos ni confirma existencias.</p>
<p>Cabecera exacta: <code>product_code;location_code;quantity;individual_identifier</code>. Para control por cantidad deja la última columna vacía. Para individual registra una fila por identificador, cantidad 1. No repitas producto/ubicación por cantidad ni identificadores. Usa punto decimal, sin separadores de miles ni fórmulas.</p>
<p>Hasta 1 MiB, 1000 filas y 500 combinaciones de producto y ubicación. Incluye todas las ubicaciones de cada producto en un único lote de apertura.</p>
<section class="card"><form method="POST" enctype="multipart/form-data" action="{{ route('initial-stock-import.preview') }}">@csrf<div class="form-grid"><div class="field"><label for="document_date">Fecha del documento</label><input id="document_date" name="document_date" type="date" value="{{ old('document_date', now(\App\Models\InventoryDocument::DISPLAY_TIMEZONE)->format('Y-m-d')) }}" required></div><div class="field"><label for="cutoff_date">Fecha de corte</label><input id="cutoff_date" name="cutoff_date" type="date" value="{{ old('cutoff_date') }}" required></div><div class="field"><label for="file">Archivo CSV UTF-8</label><input id="file" name="file" type="file" accept=".csv" required></div></div><button>Revisar archivo</button></form></section>
@endsection
