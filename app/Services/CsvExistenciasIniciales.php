<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UnidadIndividual;
use App\Support\DecimalTecnico;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CsvExistenciasIniciales
{
    public function parse(UploadedFile $file): array
    {
        if ($file->getSize() > 1048576) {
            $this->fail('El archivo supera 1 MiB.');
        }
        $stream = fopen($file->getRealPath(), 'rb');
        $pairs = [];
        $seen = [];
        try {
            $header = fgetcsv($stream, 4096, ';', '"', '');
            if ($header) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header !== ['product_code', 'location_code', 'cantidad', 'individual_identifier']) {
                $this->fail('Cabecera requerida: product_code;location_code;cantidad;individual_identifier');
            }
            $number = 1;
            while (($row = fgetcsv($stream, 8192, ';', '"', '')) !== false) {
                $number++;
                if ($number > 1001) {
                    $this->fail('Se admiten hasta 1000 filas de datos.');
                }
                if (count($row) !== 4) {
                    $this->fail("Fila {$number}: se requieren cuatro columnas.");
                }
                foreach ($row as &$value) {
                    // trim() removes NUL too: reject it before normalization.
                    $value ??= '';
                    if (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                        $this->fail("Fila {$number}: usa texto UTF-8 sin fórmulas ni caracteres de control.");
                    }
                    $value = trim($value);
                    if (preg_match('/^[=+@]/', $value)) {
                        $this->fail("Fila {$number}: usa texto UTF-8 sin fórmulas ni caracteres de control.");
                    }
                }
                unset($value);
                [$productCode, $locationCode, $cantidad, $identificador] = $row;
                $producto = Producto::where('codigo_interno', $productCode)->first();
                $ubicacion = Ubicacion::where('codigo', $locationCode)->first();
                if (! $producto || ! $ubicacion) {
                    $this->fail("Fila {$number}: producto o ubicación no existente. El archivo no crea catálogo.");
                }
                $key = $producto->id.':'.$ubicacion->id;
                if ($producto->modo_control === 'individual') {
                    $unidad = UnidadIndividual::where('identificador', $identificador)->where('producto_id', $producto->id)->first();
                    if (! $unidad || isset($seen[$unidad->id]) || DecimalTecnico::normalize($cantidad, 'file', 0) !== '1') {
                        $this->fail("Fila {$number}: informa una unidad individual existente, no repetida, con cantidad 1.");
                    }
                    $seen[$unidad->id] = true;
                    $pairs[$key] ??= ['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => '0', 'unit_ids' => []];
                    $pairs[$key]['unit_ids'][] = $unidad->id;
                    $pairs[$key]['cantidad'] = (string) count($pairs[$key]['unit_ids']);
                } else {
                    if (isset($pairs[$key]) || $identificador !== '') {
                        $this->fail("Fila {$number}: producto/ubicación repetido o identificador no permitido para control por cantidad.");
                    }
                    $normalized = DecimalTecnico::normalize($cantidad, 'file', $producto->unidadMedida->decimales);
                    if ($normalized === null || DecimalTecnico::compare($normalized, '0') <= 0) {
                        $this->fail("Fila {$number}: informa una cantidad positiva.");
                    }
                    $pairs[$key] = ['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => $normalized, 'unit_ids' => []];
                }
                if (count($pairs) > 500) {
                    $this->fail('Se admiten hasta 500 combinaciones de producto y ubicación.');
                }
            }
        } finally {
            fclose($stream);
        }
        if (! $pairs) {
            $this->fail('El archivo no contiene líneas.');
        }

        return ['lineas' => array_values($pairs), 'sha256' => hash_file('sha256', $file->getRealPath())];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
