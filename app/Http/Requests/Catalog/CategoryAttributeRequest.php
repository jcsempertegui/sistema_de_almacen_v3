<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\CategoryAttribute;
use App\Support\TechnicalDecimal;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryAttributeRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return ($association = $this->route('category_attribute')) ? $this->user()->can('update', $association) : $this->user()->can('create', CategoryAttribute::class);
    }

    public function rules(): array
    {
        $decimal = function (string $field, mixed $value, Closure $fail) {
            try {
                TechnicalDecimal::normalize($value, $field);
            } catch (ValidationException $exception) {
                $fail($exception->errors()[$field][0]);
            }
        };

        return [
            'category_id' => ['prohibited'],
            'attribute_id' => $this->route('category_attribute') ? ['prohibited'] : ['required', 'integer', Rule::exists('attributes', 'id'), Rule::unique('category_attributes')->where('category_id', $this->route('category')->id)],
            'is_required' => ['required', 'boolean'],
            'is_identity' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'between:0,65535'],
            'min_value' => ['nullable', $decimal],
            'max_value' => ['nullable', $decimal],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['attribute_id' => 'atributo', 'category_id' => 'categoría', 'is_required' => 'obligatoriedad', 'is_identity' => 'identificación técnica', 'display_order' => 'orden', 'min_value' => 'mínimo', 'max_value' => 'máximo']);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['display_order.between' => 'El orden debe estar entre 0 y 65535.']);
    }
}
