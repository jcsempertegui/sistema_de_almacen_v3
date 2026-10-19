<?php

namespace App\Services;

use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Support\TechnicalDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryDraftValidator
{
    public function validate(array $data): array
    {
        foreach (['counterparty_id', 'counterparty_name', 'external_reference', 'notes'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]) ?: null;
            }
        }
        if (is_string($data['counterparty_id'] ?? null)) {
            $data['counterparty_id'] = mb_strtoupper($data['counterparty_id']);
        }
        $opening = ($data['kind'] ?? '') === 'opening';
        $validated = Validator::make($data, [
            'kind' => ['required', Rule::in(['receipt', 'issue', 'opening'])],
            'document_date' => ['required', 'date_format:Y-m-d'],
            'cutoff_date' => [$opening ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:document_date'],
            'counterparty_id' => [$opening ? 'nullable' : 'required', 'string', 'max:80'],
            'counterparty_name' => [$opening ? 'nullable' : 'required', 'string', 'max:200'],
            'external_reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:10000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.location_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required'],
            'lines.*.unit_ids' => ['present', 'array', 'max:1000'],
            'lines.*.unit_ids.*' => ['required', 'integer', 'min:1'],
        ], [
            'required' => 'El campo :attribute es obligatorio.', 'present' => 'Debes informar la selección de unidades (vacía para productos por cantidad).',
            'array' => 'El campo :attribute debe ser una lista.', 'integer' => 'El campo :attribute debe ser un identificador entero.',
            'min' => 'El campo :attribute no alcanza el mínimo permitido.', 'max' => 'El campo :attribute supera el límite permitido.',
            'date_format' => 'Usa una fecha válida con formato AAAA-MM-DD.', 'before_or_equal' => 'La fecha de corte no puede ser posterior a la fecha del documento.',
            'in' => 'Selecciona una opción válida.', 'string' => 'El campo :attribute debe ser texto.',
        ])->validate();
        $pairs = [];
        $units = [];
        $lines = [];
        foreach (array_values($validated['lines']) as $i => $line) {
            $pair = $line['product_id'].':'.$line['location_id'];
            if (isset($pairs[$pair])) {
                throw ValidationException::withMessages(['lines' => 'No repitas un producto y ubicación: reúne su cantidad y sus unidades en una sola línea.']);
            }
            $pairs[$pair] = true;
            $lines[] = $this->line($line, 'lines.'.$i, $units);
        }
        $validated['lines'] = $lines;

        return $validated;
    }

    public function line(array $line, string $field, array &$seen): array
    {
        $product = Product::whereKey($line['product_id'])->lockForUpdate()->first();
        $location = Location::whereKey($line['location_id'])->lockForUpdate()->first();
        $link = ProductLocation::where('product_id', $line['product_id'])->where('location_id', $line['location_id'])->lockForUpdate()->first();
        if (! $product?->is_active || ! $location?->is_active || ! $location->is_storable || ! $link?->is_active) {
            throw ValidationException::withMessages([$field => 'El producto y la ubicación deben estar activos, con asociación activa y almacenamiento permitido.']);
        }
        app(LocationHierarchy::class)->validateParent($location, true);
        $unit = MeasurementUnit::whereKey($product->measurement_unit_id)->lockForUpdate()->firstOrFail();
        if (! $unit->is_active) {
            throw ValidationException::withMessages([$field => 'La unidad de medida debe estar activa.']);
        }
        $quantity = TechnicalDecimal::normalize($line['quantity'], $field.'.quantity', $unit->decimal_places);
        if ($quantity === null || TechnicalDecimal::compare($quantity, '0') <= 0) {
            throw ValidationException::withMessages([$field.'.quantity' => 'La cantidad debe ser mayor que cero.']);
        }
        $ids = array_map('intval', $line['unit_ids']);
        if ($product->control_mode === 'individual') {
            if ($unit->dimension !== 'count' || $unit->decimal_places !== 0 || $quantity !== (string) count($ids) || ! $ids) {
                throw ValidationException::withMessages([$field => 'Para control individual usa una unidad de conteo con precisión cero y selecciona tantos identificadores como cantidad.']);
            }
            sort($ids);
            foreach ($ids as $id) {
                $identity = IndividualUnit::whereKey($id)->lockForUpdate()->first();
                if (isset($seen[$id]) || ! $identity?->is_active || $identity->product_id !== $product->id) {
                    throw ValidationException::withMessages([$field.'.unit_ids' => 'Cada unidad debe ser activa, pertenecer al producto y aparecer una sola vez en el documento.']);
                }
                $seen[$id] = true;
                if (count($seen) > 1000) {
                    throw ValidationException::withMessages(['lines' => 'Se admiten hasta 1000 identificadores por documento.']);
                }
            }
        } elseif ($ids) {
            throw ValidationException::withMessages([$field.'.unit_ids' => 'Un producto por cantidad no admite identificadores individuales.']);
        }

        return [
            'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => $quantity, 'unit_ids' => $ids,
            'measurement_unit_id' => $unit->id, 'unit_symbol' => $unit->symbol, 'decimal_places' => $unit->decimal_places, 'control_mode' => $product->control_mode,
        ];
    }
}
