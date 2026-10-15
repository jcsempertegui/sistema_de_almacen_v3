<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SearchRequest;
use App\Models\Location;
use Illuminate\Validation\Rule;

class LocationSearchRequest extends SearchRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Location::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['nullable', Rule::in(array_keys(Location::TYPES))],
            'parent_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'roots_only' => ['nullable', 'boolean'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['type' => 'tipo de ubicación', 'parent_id' => 'ubicación padre', 'roots_only' => 'solo raíces']);
    }
}
