<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudCorreccion extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $lineas = $this->input('lineas');
        if (is_array($lineas)) {
            foreach ($lineas as &$line) {
                if (is_array($line) && array_key_exists('unit_ids', $line) && in_array($line['unit_ids'], ['', null], true)) {
                    $line['unit_ids'] = [];
                }
            }unset($line);
            $this->merge(['lineas' => $lineas]);
        }
    }

    public function authorize(): bool
    {
        $clase = $this->route('clase');

        return in_array($clase, ['adjustment', 'compensation'], true) && $this->user()->tienePermiso(($clase === 'adjustment' ? 'adjustments' : 'compensations').'.'.($this->isMethod('PUT') ? 'update' : 'create'));
    }

    public function rules(): array
    {
        return ['motivo' => 'required|string|max:1000', 'fecha_documento' => 'required|date_format:Y-m-d', 'version_bloqueo' => 'nullable|integer|min:1', 'tipo_origen' => 'nullable|in:inventory-documents,deliveries,loans,returns,corrections', 'origen_id' => 'nullable|integer|min:1', 'expected_line_count' => 'nullable|integer|min:1|max:100', 'lineas' => 'nullable|array|max:100', 'lineas.*.producto_id' => 'required|integer|min:1', 'lineas.*.ubicacion_id' => 'required|integer|min:1', 'lineas.*.cantidad_contada' => 'required', 'lineas.*.unit_ids' => 'present|array|max:1000', 'lineas.*.unit_ids.*' => 'integer|min:1|distinct'];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'integer' => 'Selecciona un identificador válido.', 'array' => 'El campo :attribute debe ser una lista.', 'date_format' => 'Usa una fecha AAAA-MM-DD.', 'string' => 'El campo :attribute debe ser texto.', 'max' => 'El campo :attribute excede el límite.', 'in' => 'Selecciona una opción válida.', 'distinct' => 'No repitas identificadores.'];
    }
}
