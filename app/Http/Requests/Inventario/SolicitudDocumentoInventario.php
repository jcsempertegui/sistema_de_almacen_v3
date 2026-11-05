<?php

namespace App\Http\Requests\Inventario;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\DocumentoInventario;

class SolicitudDocumentoInventario extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        $documento = $this->route('documento');

        return $documento instanceof DocumentoInventario
            ? $this->user()->can('update', $documento)
            : $this->user()->tienePermiso($this->input('tipo') === 'opening' ? 'initial_stock.create' : 'remittances.create');
    }

    protected function prepareForValidation(): void
    {
        // An omitted HTML multiselect is an intentional empty selection.
        $lineas = $this->input('lineas');
        if (is_array($lineas)) {
            foreach ($lineas as &$line) {
                if (is_array($line)) {
                    $line['unit_ids'] ??= [];
                }
            }
            $this->merge(['lineas' => $lineas]);
        }
    }

    public function rules(): array
    {
        return ['tipo' => ['required', 'in:receipt,issue,opening'], 'lineas' => ['required', 'array', 'min:1', 'max:500'], 'expected_line_count' => ['required', 'integer', 'min:1', 'max:500'], 'version_bloqueo' => [$this->route('documento') ? 'required' : 'nullable', 'integer', 'min:1']];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['lineas' => 'líneas', 'expected_line_count' => 'cantidad de líneas del formulario', 'tipo' => 'tipo de documento', 'version_bloqueo' => 'versión del documento'];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (is_array($this->input('lineas')) && count($this->input('lineas')) !== (int) $this->input('expected_line_count')) {
                $validator->errors()->add('lineas', 'El formulario llegó incompleto. Reduce el número de líneas o usa la importación CSV para apertura.');
            }
        }];
    }
}
