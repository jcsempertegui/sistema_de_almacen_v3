<?php

namespace App\Http\Requests\OperacionesTrabajadores;

use App\Support\ValidacionOperacion;
use Illuminate\Foundation\Http\FormRequest;

class SolicitudOperacionTrabajador extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso($this->route('clase').'.'.($this->route('documento') ? 'update' : 'create')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('clase') === 'deliveries') {
            $lineas = $this->input('lineas', []);
            if (is_array($lineas)) {
                foreach ($lineas as &$line) {
                    if (is_array($line)) {
                        $line['unit_ids'] ??= [];
                    }
                }unset($line);
                $this->merge(['lineas' => $lineas]);
            }
        }
    }

    public function rules(): array
    {
        $clase = $this->route('clase');
        $rules = ['fecha_documento' => ['required', 'date_format:Y-m-d'], 'observaciones' => ['nullable', 'string', 'max:10000'], 'version_bloqueo' => ['nullable', 'integer', 'min:1'], 'expected_line_count' => ['required', 'integer', 'min:1', 'max:1000'], 'lineas' => ['required', 'array', 'min:1', 'max:1000'], 'lineas.*.ubicacion_id' => ['required', 'integer', 'min:1']];
        if ($clase === 'returns') {
            $rules += ['prestamo_id' => ['required', 'integer', 'min:1'], 'lineas.*.item_prestamo_id' => ['required', 'integer', 'min:1', 'distinct']];
        } else {
            $rules['trabajador_id'] = ['required', 'integer', 'min:1'];
        }
        if ($clase === 'loans') {
            $rules += ['fecha_limite' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_documento'], 'lineas.*.unidad_individual_id' => ['required', 'integer', 'min:1', 'distinct']];
        }
        if ($clase === 'deliveries') {
            $rules += ['lineas.*.producto_id' => ['required', 'integer', 'min:1'], 'lineas.*.cantidad' => ['required'], 'lineas.*.unit_ids' => ['present', 'array', 'max:1000'], 'lineas.*.unit_ids.*' => ['integer', 'min:1']];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ValidacionOperacion::MESSAGES;
    }
}
