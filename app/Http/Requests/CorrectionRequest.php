<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');
        if (is_array($lines)) {
            foreach ($lines as &$line) {
                if (is_array($line) && array_key_exists('unit_ids', $line) && in_array($line['unit_ids'], ['', null], true)) {
                    $line['unit_ids'] = [];
                }
            }unset($line);
            $this->merge(['lines' => $lines]);
        }
    }

    public function authorize(): bool
    {
        $kind = $this->route('kind');

        return in_array($kind, ['adjustment', 'compensation'], true) && $this->user()->hasPermission(($kind === 'adjustment' ? 'adjustments' : 'compensations').'.'.($this->isMethod('PUT') ? 'update' : 'create'));
    }

    public function rules(): array
    {
        return ['reason' => 'required|string|max:1000', 'document_date' => 'required|date_format:Y-m-d', 'lock_version' => 'nullable|integer|min:1', 'source_kind' => 'nullable|in:inventory-documents,deliveries,loans,returns,corrections', 'source_id' => 'nullable|integer|min:1', 'expected_line_count' => 'nullable|integer|min:1|max:100', 'lines' => 'nullable|array|max:100', 'lines.*.product_id' => 'required|integer|min:1', 'lines.*.location_id' => 'required|integer|min:1', 'lines.*.counted_quantity' => 'required', 'lines.*.unit_ids' => 'present|array|max:1000', 'lines.*.unit_ids.*' => 'integer|min:1|distinct'];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'integer' => 'Selecciona un identificador válido.', 'array' => 'El campo :attribute debe ser una lista.', 'date_format' => 'Usa una fecha AAAA-MM-DD.', 'string' => 'El campo :attribute debe ser texto.', 'max' => 'El campo :attribute excede el límite.', 'in' => 'Selecciona una opción válida.', 'distinct' => 'No repitas identificadores.'];
    }
}
