<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\SgaFormRequest;
use App\Models\InventoryDocument;

class InventoryDocumentRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof InventoryDocument
            ? $this->user()->can('update', $document)
            : $this->user()->hasPermission($this->input('kind') === 'opening' ? 'initial_stock.create' : 'remittances.create');
    }

    protected function prepareForValidation(): void
    {
        // An omitted HTML multiselect is an intentional empty selection.
        $lines = $this->input('lines');
        if (is_array($lines)) {
            foreach ($lines as &$line) {
                if (is_array($line)) {
                    $line['unit_ids'] ??= [];
                }
            }
            $this->merge(['lines' => $lines]);
        }
    }

    public function rules(): array
    {
        return ['kind' => ['required', 'in:receipt,issue,opening'], 'lines' => ['required', 'array', 'min:1', 'max:500'], 'expected_line_count' => ['required', 'integer', 'min:1', 'max:500'], 'lock_version' => [$this->route('document') ? 'required' : 'nullable', 'integer', 'min:1']];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['lines' => 'líneas', 'expected_line_count' => 'cantidad de líneas del formulario', 'kind' => 'tipo de documento', 'lock_version' => 'versión del documento'];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (is_array($this->input('lines')) && count($this->input('lines')) !== (int) $this->input('expected_line_count')) {
                $validator->errors()->add('lines', 'El formulario llegó incompleto. Reduce el número de líneas o usa la importación CSV para apertura.');
            }
        }];
    }
}
