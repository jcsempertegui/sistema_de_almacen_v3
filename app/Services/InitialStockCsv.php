<?php

namespace App\Services;

use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\Product;
use App\Support\TechnicalDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class InitialStockCsv
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
            if ($header !== ['product_code', 'location_code', 'quantity', 'individual_identifier']) {
                $this->fail('Cabecera requerida: product_code;location_code;quantity;individual_identifier');
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
                    $value = trim($value ?? '');
                    if (! mb_check_encoding($value, 'UTF-8') || preg_match('/^[=+@]/', $value) || str_contains($value, "\0")) {
                        $this->fail("Fila {$number}: usa texto UTF-8 sin fórmulas ni caracteres de control.");
                    }
                }
                unset($value);
                [$productCode, $locationCode, $quantity, $identifier] = $row;
                $product = Product::where('internal_code', $productCode)->first();
                $location = Location::where('code', $locationCode)->first();
                if (! $product || ! $location) {
                    $this->fail("Fila {$number}: producto o ubicación no existente. El archivo no crea catálogo.");
                }
                $key = $product->id.':'.$location->id;
                if ($product->control_mode === 'individual') {
                    $unit = IndividualUnit::where('identifier', $identifier)->where('product_id', $product->id)->first();
                    if (! $unit || isset($seen[$unit->id]) || TechnicalDecimal::normalize($quantity, 'file', 0) !== '1') {
                        $this->fail("Fila {$number}: informa una unidad individual existente, no repetida, con cantidad 1.");
                    }
                    $seen[$unit->id] = true;
                    $pairs[$key] ??= ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '0', 'unit_ids' => []];
                    $pairs[$key]['unit_ids'][] = $unit->id;
                    $pairs[$key]['quantity'] = (string) count($pairs[$key]['unit_ids']);
                } else {
                    if (isset($pairs[$key]) || $identifier !== '') {
                        $this->fail("Fila {$number}: producto/ubicación repetido o identificador no permitido para control por cantidad.");
                    }
                    $normalized = TechnicalDecimal::normalize($quantity, 'file', $product->measurementUnit->decimal_places);
                    if ($normalized === null || TechnicalDecimal::compare($normalized, '0') <= 0) {
                        $this->fail("Fila {$number}: informa una cantidad positiva.");
                    }
                    $pairs[$key] = ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => $normalized, 'unit_ids' => []];
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

        return ['lines' => array_values($pairs), 'sha256' => hash_file('sha256', $file->getRealPath())];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
