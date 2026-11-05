<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\UnidadMedida;
use App\Support\DecimalTecnico;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ValidadorBorradorInventario
{
    public function validate(array $data): array
    {
        foreach (['identificacion_contraparte', 'nombre_contraparte', 'referencia_externa', 'observaciones'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]) ?: null;
            }
        }
        if (is_string($data['identificacion_contraparte'] ?? null)) {
            $data['identificacion_contraparte'] = mb_strtoupper($data['identificacion_contraparte']);
        }
        $opening = ($data['tipo'] ?? '') === 'opening';
        $validated = Validator::make($data, [
            'tipo' => ['required', Rule::in(['receipt', 'issue', 'opening'])],
            'fecha_documento' => ['required', 'date_format:Y-m-d'],
            'fecha_corte' => [$opening ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:fecha_documento'],
            'identificacion_contraparte' => [$opening ? 'nullable' : 'required', 'string', 'max:80'],
            'nombre_contraparte' => [$opening ? 'nullable' : 'required', 'string', 'max:200'],
            'referencia_externa' => ['nullable', 'string', 'max:100'], 'observaciones' => ['nullable', 'string', 'max:10000'],
            'lineas' => ['required', 'array', 'min:1', 'max:500'],
            'lineas.*.producto_id' => ['required', 'integer', 'min:1'],
            'lineas.*.ubicacion_id' => ['required', 'integer', 'min:1'],
            'lineas.*.cantidad' => ['required'],
            'lineas.*.unit_ids' => ['present', 'array', 'max:1000'],
            'lineas.*.unit_ids.*' => ['required', 'integer', 'min:1'],
        ], [
            'required' => 'El campo :attribute es obligatorio.', 'present' => 'Debes informar la selección de unidades (vacía para productos por cantidad).',
            'array' => 'El campo :attribute debe ser una lista.', 'integer' => 'El campo :attribute debe ser un identificador entero.',
            'min' => 'El campo :attribute no alcanza el mínimo permitido.', 'max' => 'El campo :attribute supera el límite permitido.',
            'date_format' => 'Usa una fecha válida con formato AAAA-MM-DD.', 'before_or_equal' => 'La fecha de corte no puede ser posterior a la fecha del documento.',
            'in' => 'Selecciona una opción válida.', 'string' => 'El campo :attribute debe ser texto.',
        ])->validate();
        $pairs = [];
        $unidades = [];
        $lineas = [];
        foreach (array_values($validated['lineas']) as $i => $line) {
            $pair = $line['producto_id'].':'.$line['ubicacion_id'];
            if (isset($pairs[$pair])) {
                throw ValidationException::withMessages(['lineas' => 'No repitas un producto y ubicación: reúne su cantidad y sus unidades en una sola línea.']);
            }
            $pairs[$pair] = true;
            $lineas[] = $this->line($line, 'lineas.'.$i, $unidades);
        }
        $validated['lineas'] = $lineas;

        return $validated;
    }

    public function line(array $line, string $field, array &$seen): array
    {
        $producto = Producto::whereKey($line['producto_id'])->lockForUpdate()->first();
        $ubicacion = Ubicacion::whereKey($line['ubicacion_id'])->lockForUpdate()->first();
        $link = UbicacionProducto::where('producto_id', $line['producto_id'])->where('ubicacion_id', $line['ubicacion_id'])->lockForUpdate()->first();
        if (! $producto?->activo || ! $ubicacion?->activo || ! $ubicacion->permite_almacenar || ! $link?->activo) {
            throw ValidationException::withMessages([$field => 'El producto y la ubicación deben estar activos, con asociación activa y almacenamiento permitido.']);
        }
        app(JerarquiaUbicaciones::class)->validateParent($ubicacion, true);
        $unidad = UnidadMedida::whereKey($producto->unidad_medida_id)->lockForUpdate()->firstOrFail();
        if (! $unidad->activo) {
            throw ValidationException::withMessages([$field => 'La unidad de medida debe estar activa.']);
        }
        $cantidad = DecimalTecnico::normalize($line['cantidad'], $field.'.cantidad', $unidad->decimales);
        if ($cantidad === null || DecimalTecnico::compare($cantidad, '0') <= 0) {
            throw ValidationException::withMessages([$field.'.cantidad' => 'La cantidad debe ser mayor que cero.']);
        }
        $ids = array_map('intval', $line['unit_ids']);
        if ($producto->modo_control === 'individual') {
            if ($unidad->dimension !== 'count' || $unidad->decimales !== 0 || $cantidad !== (string) count($ids) || ! $ids) {
                throw ValidationException::withMessages([$field => 'Para control individual usa una unidad de conteo con precisión cero y selecciona tantos identificadores como cantidad.']);
            }
            sort($ids);
            foreach ($ids as $id) {
                $identity = UnidadIndividual::whereKey($id)->lockForUpdate()->first();
                if (isset($seen[$id]) || ! $identity?->activo || $identity->producto_id !== $producto->id) {
                    throw ValidationException::withMessages([$field.'.unit_ids' => 'Cada unidad debe ser activa, pertenecer al producto y aparecer una sola vez en el documento.']);
                }
                $seen[$id] = true;
                if (count($seen) > 1000) {
                    throw ValidationException::withMessages(['lineas' => 'Se admiten hasta 1000 identificadores por documento.']);
                }
            }
        } elseif ($ids) {
            throw ValidationException::withMessages([$field.'.unit_ids' => 'Un producto por cantidad no admite identificadores individuales.']);
        }

        return [
            'producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => $cantidad, 'unit_ids' => $ids,
            'unidad_medida_id' => $unidad->id, 'simbolo_unidad' => $unidad->simbolo, 'decimales' => $unidad->decimales, 'modo_control' => $producto->modo_control,
        ];
    }
}
